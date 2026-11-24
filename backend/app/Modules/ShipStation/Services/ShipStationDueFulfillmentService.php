<?php

namespace App\Modules\ShipStation\Services;

use App\Models\Fulfillment;
use App\Models\Order;
use App\Modules\Order\DTOs\WarehouseShipmentResponseDTO;
use App\Modules\Order\Enums\DeliveryStatus;
use App\Modules\Order\Enums\FulfillmentRecordStatus;
use App\Modules\Order\Enums\ShipmentStatus;
use App\Modules\Order\Services\OrderTestDetector;
use App\Modules\ShipStation\Exceptions\ShipStationApiException;
use App\Modules\ShipStation\Support\ShipStationFulfillmentContext;
use App\Modules\ShipStation\Support\ShipStationSubmissionDecision;
use Illuminate\Support\Facades\Log;

class ShipStationDueFulfillmentService
{
    public function __construct(
        private readonly ShipStationConfigResolver $config,
        private readonly ShipStationMockFulfillmentService $mockFulfillment,
        private readonly OrderTestDetector $testOrders,
        private readonly ShipStationAutoLabelEligibilityService $autoLabel,
        private readonly ShipStationFulfillmentContext $context,
        private readonly ShipStationRebillGuard $rebillGuard,
        private readonly ShipStationOrderEligibilityService $eligibility,
        private readonly ShipStationAutomaticFulfillmentGateService $automaticFulfillmentGate,
    ) {}

    public function completeDueFulfillment(Fulfillment $fulfillment): WarehouseShipmentResponseDTO
    {
        if (! $this->config->isEnabled()) {
            throw new ShipStationApiException('ShipStation integration is disabled.');
        }

        $fulfillment->loadMissing('order.items');
        $order = $fulfillment->order;

        if ($order === null) {
            throw new ShipStationApiException('Fulfillment is not linked to an order.');
        }

        $this->rebillGuard->assertAllowed($order, 'due_fulfillment');

        $submissionDecision = $this->eligibility->evaluate($order);

        if (! $submissionDecision->eligible) {
            $this->eligibility->markSubmissionSkipped($order, $submissionDecision, $fulfillment->id);

            return $this->skippedSubmissionResponse($submissionDecision);
        }

        if (! $this->config->isMockMode() && ! $this->config->isRealMode()) {
            throw new ShipStationApiException('ShipStation V2 fulfillment requires mock or real mode.');
        }

        if (! $this->context->manualOverride && $this->automaticFulfillmentGate->blocksAutomaticFulfillment()) {
            Log::info('ShipStation due fulfillment skipped — automatic fulfillment disabled globally.', [
                'order_id' => $order->id,
                'fulfillment_id' => $fulfillment->id,
                'order_number' => $order->order_number,
                'skip_reason' => ShipStationAutomaticFulfillmentGateService::SKIP_REASON,
            ]);

            return $this->automaticFulfillmentDisabledResponse();
        }

        $integration = $order->shipStationIntegration;
        $shipmentId = (string) ($integration?->external_shipment_id ?? '');

        if ($shipmentId === '') {
            Log::info('ShipStation due fulfillment skipped — order has no shipment yet (manual Create Shipment required).', [
                'order_id' => $order->id,
                'fulfillment_id' => $fulfillment->id,
                'order_number' => $order->order_number,
            ]);

            return $this->missingShipmentResponse();
        }

        if ($fulfillment->status === FulfillmentRecordStatus::Cancelled) {
            Log::info('Cancelled fulfillment — ShipStation label creation skipped.', [
                'order_id' => $order->id,
                'fulfillment_id' => $fulfillment->id,
            ]);

            return $this->shipmentOnlyResponse($shipmentId);
        }

        if (! $this->autoLabel->isOrderEligibleForFulfillment($order)) {
            Log::info('Order ineligible for ShipStation fulfillment — label creation skipped.', [
                'order_id' => $order->id,
                'payment_status' => $order->payment_status->value,
                'order_status' => $order->status->value,
                'manual_override' => $this->context->manualOverride,
            ]);

            if (! $this->context->manualOverride) {
                return $this->shipmentOnlyResponse($shipmentId);
            }
        }

        // Automatic cutoff only. Manual admin Force Submit sets manualOverride=true.
        if (! $this->context->manualOverride && ! $this->autoLabel->isOrderEligibleForAutomaticLabel($order)) {
            Log::info('Order excluded from automatic ShipStation label workflow.', [
                'order_id' => $order->id,
                'created_at' => $order->created_at?->toIso8601String(),
                'activation_cutoff' => $this->autoLabel->activationTimestamp()?->toIso8601String(),
                'feature_active' => $this->autoLabel->isFeatureActivated(),
            ]);

            return $this->shipmentOnlyResponse($shipmentId);
        }

        Log::info('ShipStation automatic label workflow eligible.', [
            'order_id' => $order->id,
            'fulfillment_id' => $fulfillment->id,
            'shipment_id' => $shipmentId,
            'manual_override' => $this->context->manualOverride,
        ]);

        return $this->completeRealOrderFulfillment($fulfillment, $order->fresh(['shipStationIntegration', 'fulfillments', 'transactions']));
    }

    private function skippedSubmissionResponse(ShipStationSubmissionDecision $decision): WarehouseShipmentResponseDTO
    {
        return new WarehouseShipmentResponseDTO(
            warehouseReference: '',
            trackingNumber: '',
            carrier: '',
            shipmentStatus: ShipmentStatus::Pending,
            deliveryStatus: DeliveryStatus::Pending,
            raw: [
                'provider' => 'shipstation',
                'skipped' => true,
                'skip_reason' => $decision->reasonCode,
                'landing_override_slug' => $decision->landingOverrideSlug,
            ],
            shipmentOnly: true,
            skipCustomerNotification: true,
        );
    }

    private function automaticFulfillmentDisabledResponse(): WarehouseShipmentResponseDTO
    {
        return new WarehouseShipmentResponseDTO(
            warehouseReference: '',
            trackingNumber: '',
            carrier: '',
            shipmentStatus: ShipmentStatus::Pending,
            deliveryStatus: DeliveryStatus::Pending,
            raw: [
                'provider' => 'shipstation',
                'skipped' => true,
                'skip_reason' => ShipStationAutomaticFulfillmentGateService::SKIP_REASON,
                'label_skipped' => true,
            ],
            shipmentOnly: true,
            skipCustomerNotification: true,
        );
    }

    private function missingShipmentResponse(): WarehouseShipmentResponseDTO
    {
        return new WarehouseShipmentResponseDTO(
            warehouseReference: '',
            trackingNumber: '',
            carrier: '',
            shipmentStatus: ShipmentStatus::Pending,
            deliveryStatus: DeliveryStatus::Pending,
            raw: [
                'provider' => 'shipstation',
                'skipped' => true,
                'skip_reason' => 'missing_shipment',
                'label_skipped' => true,
            ],
            shipmentOnly: true,
            skipCustomerNotification: true,
        );
    }

    private function shipmentOnlyResponse(string $shipmentId, bool $testOrder = false): WarehouseShipmentResponseDTO
    {
        return new WarehouseShipmentResponseDTO(
            warehouseReference: $shipmentId,
            trackingNumber: '',
            carrier: '',
            shipmentStatus: ShipmentStatus::Pending,
            deliveryStatus: DeliveryStatus::Pending,
            raw: [
                'provider' => 'shipstation',
                'mode' => $this->config->isMockMode() ? 'mock' : 'real',
                'test_order' => $testOrder,
                'label_skipped' => true,
                'auto_label_skipped' => ! $testOrder,
                'shipment_id' => $shipmentId,
            ],
            shipmentOnly: true,
            skipCustomerNotification: true,
        );
    }

    private function completeRealOrderFulfillment(Fulfillment $fulfillment, Order $order): WarehouseShipmentResponseDTO
    {
        $fulfillment = $fulfillment->fresh(['order.items']);
        $integration = $order->shipStationIntegration;
        $shipmentId = (string) ($integration?->external_shipment_id ?? '');

        $hasLabel = filled($integration?->external_label_id);
        $hasTracking = filled($fulfillment->tracking_number)
            || filled($integration?->tracking_number);

        if (! $hasLabel && ! $hasTracking) {
            $labelResult = $this->mockFulfillment->createLabel(
                $order->fresh(['shipStationIntegration']),
                $fulfillment,
            );

            $order = $labelResult['order']->fresh(['shipStationIntegration', 'fulfillments', 'transactions']);
            $fulfillment = ($labelResult['fulfillment'] ?? $fulfillment)->fresh(['order.items']);
            $integration = $order->shipStationIntegration;
            $hasLabel = filled($integration?->external_label_id);
            $hasTracking = filled($fulfillment->tracking_number)
                || filled($integration?->tracking_number);
        }

        if ($hasLabel && ! $hasTracking) {
            $tracked = $this->mockFulfillment->getTracking(
                $order->fresh(['shipStationIntegration', 'fulfillments', 'transactions']),
            );

            $order = $tracked['order']->fresh(['shipStationIntegration', 'fulfillments', 'transactions']);
            $fulfillment = $order->fulfillments()
                ->whereKey($fulfillment->id)
                ->first() ?? $fulfillment->fresh(['order.items']);
        }

        $integration = $order->shipStationIntegration;
        $trackingNumber = (string) ($fulfillment->tracking_number ?? $integration?->tracking_number ?? '');

        if ($trackingNumber === '') {
            throw new ShipStationApiException('ShipStation label was created but no tracking number is available yet.');
        }

        return new WarehouseShipmentResponseDTO(
            warehouseReference: (string) ($fulfillment->warehouse_reference ?? $integration?->external_shipment_id ?? $shipmentId),
            trackingNumber: $trackingNumber,
            carrier: (string) ($fulfillment->carrier ?? $integration?->carrier ?? 'shipstation'),
            shipmentStatus: ShipmentStatus::Shipped,
            deliveryStatus: DeliveryStatus::InTransit,
            raw: [
                'provider' => 'shipstation',
                'mode' => $this->config->isMockMode() ? 'mock' : 'real',
                'shipment_id' => $integration?->external_shipment_id,
                'label_id' => $integration?->external_label_id,
                'label_url' => $integration?->label_url,
                'response' => $fulfillment->warehouse_response,
            ],
            shipmentOnly: false,
            skipCustomerNotification: false,
        );
    }
}
