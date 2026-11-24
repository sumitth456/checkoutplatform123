<?php

namespace App\Modules\ShipStation\Services;

use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\ShipStationIntegrationLog;
use App\Modules\Fulfillment\Services\FulfillmentOrchestratorService;
use App\Modules\Order\Enums\DeliveryStatus;
use App\Modules\Order\Enums\FulfillmentRecordStatus;
use App\Modules\Order\Enums\ShipmentStatus;
use App\Modules\ShipStation\Contracts\ShipStationServiceInterface;
use App\Modules\ShipStation\Enums\ShipStationOrderStatus;
use App\Modules\ShipStation\Exceptions\ShipStationApiException;
use App\Modules\ShipStation\Exceptions\ShipStationSubmissionSkippedException;
use Illuminate\Support\Facades\Log;

class ShipStationLabelService
{
    public function __construct(

        private readonly ShipStationConfigResolver $config,

        private readonly ShipStationServiceInterface $api,

        private readonly ShipStationOrderSyncService $orderSync,

        private readonly FulfillmentOrchestratorService $orchestrator,

        private readonly ShipStationIntegrationRecordService $integrationRecords,

        private readonly ShipStationMockFulfillmentService $mockFulfillment,

        private readonly ShipStationOrderEligibilityService $eligibility,

    ) {}

    /**
     * @param  array{carrierCode?: string, serviceCode?: string, packageCode?: string, weight?: float, testLabel?: bool, shipmentId?: string}  $options
     */
    public function createLabel(Fulfillment $fulfillment, array $options = []): Fulfillment
    {

        if (! $this->config->isEnabled()) {

            throw new ShipStationApiException('ShipStation integration is disabled.');
        }

        $fulfillment->loadMissing(['order.items', 'items']);

        $order = $fulfillment->order;

        if ($order === null) {

            throw new ShipStationApiException('Fulfillment is not linked to an order.');
        }

        if (! $this->eligibility->isEligibleForShipStationSubmission($order)) {
            throw new ShipStationSubmissionSkippedException(
                $this->eligibility->evaluate($order),
                action: 'label',
            );
        }

        // Manual admin workflow — no automatic cutoff applied here.

        if ($this->config->isMockMode() || $this->config->isRealMode()) {
            $order = $order->fresh(['shipStationIntegration', 'items']);

            if (blank($order->shipStationIntegration?->external_shipment_id)) {
                throw new ShipStationApiException(
                    'Create a ShipStation shipment before creating a label.'
                );
            }

            $result = $this->mockFulfillment->createLabel($order, $fulfillment);

            return $result['fulfillment'] ?? $fulfillment->fresh();
        }

        $order = $this->orderSync->sync($order, force: false);

        if (! filled($order->shipstation_order_id)) {

            throw new ShipStationApiException('Order is not synced to ShipStation.');
        }

        $shipTo = $this->shipToAddress($order);

        $carrierCode = $options['carrierCode'] ?? (string) ($fulfillment->carrier ?: 'usps');

        $serviceCode = $options['serviceCode'] ?? (string) ($fulfillment->shipping_method ?: 'usps_priority_mail');

        $packageCode = $options['packageCode'] ?? 'package';

        $weight = (float) ($options['weight'] ?? 1);

        $payload = [

            'orderId' => (int) $order->shipstation_order_id,

            'carrierCode' => $carrierCode,

            'serviceCode' => $serviceCode,

            'packageCode' => $packageCode,

            'confirmation' => 'none',

            'shipDate' => now()->toDateString(),

            'weight' => [

                'value' => $weight,

                'units' => 'ounces',

            ],

            'testLabel' => (bool) ($options['testLabel'] ?? false),

            'shipTo' => $shipTo,

        ];

        try {

            $response = $this->api->createLabel($payload);

            $trackingNumber = (string) ($response['trackingNumber'] ?? '');

            $shipmentId = (int) ($response['shipmentId'] ?? 0);

            $labelData = $response['labelData'] ?? null;

            $metadata = $fulfillment->metadata ?? [];

            $metadata['shipstation'] = array_merge($metadata['shipstation'] ?? [], [

                'shipment_id' => $shipmentId,

                'label_id' => $response['labelId'] ?? null,

                'carrier_code' => $carrierCode,

                'service_code' => $serviceCode,

                'shipping_cost' => $response['shipmentCost'] ?? null,

                'label_created_at' => now()->toIso8601String(),

            ]);

            $fulfillment->update([

                'warehouse_reference' => $shipmentId > 0 ? (string) $shipmentId : $fulfillment->warehouse_reference,

                'tracking_number' => $trackingNumber ?: $fulfillment->tracking_number,

                'carrier' => $carrierCode,

                'shipping_method' => $serviceCode,

                'shipment_status' => ShipmentStatus::Processing,

                'delivery_status' => DeliveryStatus::Pending,

                'status' => FulfillmentRecordStatus::Shipped,

                'shipped_at' => now(),

                'warehouse_response' => $this->sanitizeResponse($response),

                'metadata' => $metadata,

                'failure_reason' => null,

            ]);

            if ($trackingNumber !== '') {

                $this->orchestrator->updateTracking($fulfillment->fresh(), $trackingNumber, $carrierCode, $serviceCode);

            }

            $this->integrationRecords->upsertForOrder(

                order: $order->fresh(),

                status: ShipStationOrderStatus::Shipped,

                externalOrderId: (string) $order->shipstation_order_id,

                externalShipmentId: $shipmentId > 0 ? (string) $shipmentId : null,

                trackingNumber: $trackingNumber ?: null,

                carrier: $carrierCode,

                service: $serviceCode,

                labelStatus: 'created',

                requestPayload: $payload,

                responsePayload: $this->sanitizeResponse($response),

            );

            ShipStationIntegrationLog::query()->create([

                'request_type' => 'create_label',

                'order_id' => $order->id,

                'fulfillment_id' => $fulfillment->id,

                'shipstation_order_id' => (int) $order->shipstation_order_id,

                'shipstation_shipment_id' => $shipmentId ?: null,

                'success' => true,

                'context' => [

                    'tracking_number' => $trackingNumber,

                    'has_label_data' => filled($labelData),

                ],

            ]);

            return $fulfillment->fresh();

        } catch (ShipStationApiException $exception) {

            ShipStationIntegrationLog::query()->create([

                'request_type' => 'create_label',

                'order_id' => $order->id,

                'fulfillment_id' => $fulfillment->id,

                'shipstation_order_id' => (int) $order->shipstation_order_id,

                'success' => false,

                'http_status' => $exception->httpStatus,

                'error_message' => $exception->getMessage(),

            ]);

            Log::warning('ShipStation label creation failed', [

                'fulfillment_id' => $fulfillment->id,

                'order_id' => $order->id,

                'message' => $exception->getMessage(),

            ]);

            throw $exception;
        }

    }

    /**
     * @return array<string, mixed>
     */
    private function shipToAddress(Order $order): array
    {

        $address = $order->shipping_address ?? $order->billing_address ?? [];

        return [

            'name' => trim((string) (($address['first_name'] ?? '').' '.($address['last_name'] ?? ''))) ?: 'Customer',

            'street1' => (string) ($address['line1'] ?? $address['address_line1'] ?? ''),

            'street2' => (string) ($address['line2'] ?? $address['address_line2'] ?? ''),

            'city' => (string) ($address['city'] ?? ''),

            'state' => (string) ($address['state'] ?? $address['region'] ?? ''),

            'postalCode' => (string) ($address['postal_code'] ?? $address['zip'] ?? ''),

            'country' => strtoupper((string) ($address['country'] ?? $order->country_code ?? 'US')),

            'phone' => (string) ($order->customer_phone ?? ''),

        ];

    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    private function sanitizeResponse(array $response): array
    {

        unset($response['labelData']);

        return $response;

    }
}
