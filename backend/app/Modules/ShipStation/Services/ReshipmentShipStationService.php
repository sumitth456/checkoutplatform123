<?php

namespace App\Modules\ShipStation\Services;

use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\FulfillmentShipStationIntegration;
use App\Models\ReshipRequest;
use App\Models\ShipStationIntegrationLog;
use App\Modules\Fulfillment\Exceptions\ReshipmentException;
use App\Modules\Order\Enums\FulfillmentRecordStatus;
use App\Modules\ShipStation\Exceptions\ShipStationApiException;
use App\Modules\ShipStation\Support\ShipStationV2PayloadBuilder;
use App\Modules\ShipStation\Support\ShipStationV2ShipmentLookup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class ReshipmentShipStationService
{
    public function __construct(
        private readonly ShipStationConfigResolver $config,
        private readonly ShipStationRebillGuard $rebillGuard,
        private readonly ShipStationV2ApiClient $v2,
        private readonly ShipStationV2PayloadBuilder $payloadBuilder,
        private readonly ShipStationV2ShipmentLookup $shipmentLookup,
    ) {}

    /**
     * @return array{integration: FulfillmentShipStationIntegration, response: array<string, mixed>, skipped: bool}
     */
    public function createShipmentForReshipment(ReshipRequest $reship): array
    {
        if (! $this->config->isEnabled()) {
            throw new ShipStationApiException('ShipStation integration is disabled.');
        }

        if (! $this->config->isMockMode() && ! $this->config->isRealMode()) {
            throw new ShipStationApiException('ShipStation V2 is required for reshipment shipments.');
        }

        $reship->loadMissing(['order', 'newFulfillment.items.orderItem.product', 'newFulfillment.items.variant']);

        $order = $reship->order;
        $fulfillment = $reship->newFulfillment;

        if ($order === null || $fulfillment === null) {
            throw new ReshipmentException('Reshipment is missing order or replacement fulfillment.');
        }

        $this->rebillGuard->assertAllowed($order, 'reshipment_create_shipment');

        $reference = (string) ($reship->reference ?? '');

        if ($reference === '') {
            throw new ReshipmentException('Reshipment reference is missing.');
        }

        return DB::transaction(function () use ($order, $fulfillment, $reship, $reference): array {
            $existing = FulfillmentShipStationIntegration::query()
                ->where('fulfillment_id', $fulfillment->id)
                ->where('provider', 'shipstation')
                ->lockForUpdate()
                ->first();

            if ($existing !== null && filled($existing->external_shipment_id)) {
                return [
                    'integration' => $existing->fresh(),
                    'response' => is_array($existing->metadata['last_response'] ?? null) ? $existing->metadata['last_response'] : [],
                    'skipped' => true,
                ];
            }

            $remote = $this->shipmentLookup->findByExternalShipmentId($reference, $order->id);

            if ($remote !== null) {
                return $this->persistLinkedShipment($order, $fulfillment, $reship, $reference, $remote, true);
            }

            $this->config->assertWarehouseConfiguredForRealShipments();
            $this->config->assertStoreConfiguredForRealShipments();

            $payload = $this->payloadBuilder->buildReshipmentCreateShipmentsPayload($order, $fulfillment, $reference);
            $response = $this->v2->createShipments($payload, $order->id);
            $shipment = $this->normalizeShipment($response);
            $shipmentId = (string) ($shipment['shipment_id'] ?? '');

            if ($shipmentId === '') {
                throw new ShipStationApiException('ShipStation did not return a shipment ID for the reshipment.');
            }

            Log::info('ShipStation reshipment shipment created.', [
                'order_id' => $order->id,
                'fulfillment_id' => $fulfillment->id,
                'reference' => $reference,
                'shipment_id' => $shipmentId,
            ]);

            $result = $this->persistLinkedShipment($order, $fulfillment, $reship, $reference, $shipment, false);

            $fulfillment->update([
                'status' => FulfillmentRecordStatus::Processing,
                'processing_at' => now(),
                'metadata' => array_merge($fulfillment->metadata ?? [], [
                    'shipstation' => [
                        'shipment_id' => $shipmentId,
                        'external_reference' => $reference,
                    ],
                ]),
            ]);

            ShipStationIntegrationLog::query()->create([
                'request_type' => 'reshipment_create_shipment',
                'order_id' => $order->id,
                'fulfillment_id' => $fulfillment->id,
                'shipstation_shipment_id' => $shipmentId,
                'success' => true,
                'context' => [
                    'reship_request_id' => $reship->id,
                    'reference' => $reference,
                ],
                'request_payload' => $payload,
                'response_payload' => $response,
            ]);

            $reship->update(['status' => 'processing']);

            return array_merge($result, ['skipped' => false]);
        });
    }

    /**
     * @param  array<string, mixed>  $shipment
     * @return array{integration: FulfillmentShipStationIntegration, response: array<string, mixed>, skipped: bool}
     */
    private function persistLinkedShipment(
        Order $order,
        Fulfillment $fulfillment,
        ReshipRequest $reship,
        string $reference,
        array $shipment,
        bool $linkedExisting,
    ): array {
        $shipmentId = (string) ($shipment['shipment_id'] ?? '');

        $integration = FulfillmentShipStationIntegration::query()->updateOrCreate(
            [
                'fulfillment_id' => $fulfillment->id,
                'provider' => 'shipstation',
            ],
            [
                'order_id' => $order->id,
                'reship_request_id' => $reship->id,
                'external_reference' => $reference,
                'external_shipment_id' => $shipmentId !== '' ? $shipmentId : null,
                'service_code' => (string) ($shipment['service_code'] ?? null),
                'remote_shipment_status' => (string) ($shipment['shipment_status'] ?? null),
                'last_synced_at' => now(),
                'metadata' => [
                    'original_order_number' => $order->order_number,
                    'linked_existing' => $linkedExisting,
                    'last_response' => $shipment,
                ],
            ],
        );

        return [
            'integration' => $integration->fresh(),
            'response' => $shipment,
            'skipped' => $linkedExisting,
        ];
    }

    /**
     * @param  array<string, mixed>  $remote
     * @return array<string, mixed>
     */
    private function normalizeShipment(array $remote): array
    {
        if (isset($remote['shipments'][0]) && is_array($remote['shipments'][0])) {
            return $remote['shipments'][0];
        }

        return $remote;
    }
}