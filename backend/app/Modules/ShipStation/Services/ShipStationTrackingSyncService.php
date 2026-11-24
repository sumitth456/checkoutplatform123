<?php

namespace App\Modules\ShipStation\Services;

use App\Models\Order;
use App\Models\ShipStationIntegrationLog;
use App\Models\ShipStationWebhookReceipt;
use App\Modules\ShipStation\Support\ShipStationTrackingSyncGate;

final class ShipStationTrackingSyncService
{
    public function __construct(
        private readonly ShipStationConfigResolver $config,
        private readonly ShipStationTrackingSyncGate $gate,
        private readonly ShipStationV2ReadOnlyClient $readOnly,
        private readonly ShipStationV2RemoteShipmentSyncApplier $applier,
        private readonly ShipStationSyncCoordinator $coordinator,
    ) {}

    public function syncReceipt(ShipStationWebhookReceipt $receipt): array
    {
        if ($receipt->order_id === null) {
            return ['ok' => false, 'reason' => 'missing_order_id'];
        }

        if (! $this->config->isEnabled()) {
            return ['skipped' => true, 'reason' => 'shipstation_disabled'];
        }

        if (! $this->gate->isEnabled()) {
            return ['skipped' => true, 'reason' => 'global_tracking_disabled'];
        }

        $order = Order::query()->with(['shipStationIntegration', 'fulfillments'])->find($receipt->order_id);
        if ($order === null) {
            return ['ok' => false, 'reason' => 'order_not_found'];
        }

        $lock = $this->coordinator->lockForOrder($order->id);
        if (! $lock->get()) {
            return ['ok' => false, 'reason' => 'lock_busy', 'retryable' => true];
        }

        try {
            return $this->syncOrderWithReceipt($order, $receipt);
        } finally {
            $lock->release();
        }
    }

    private function syncOrderWithReceipt(Order $order, ShipStationWebhookReceipt $receipt): array
    {
        $integration = $order->shipStationIntegration;
        $shipmentId = (string) ($integration?->external_shipment_id ?? '');

        if ($shipmentId === '') {
            return ['skipped' => true, 'reason' => 'missing_shipment_id'];
        }

        try {
            $remote = $this->readOnly->getShipment($shipmentId, $order->id);
        } catch (\Throwable $exception) {
            ShipStationIntegrationLog::query()->create([
                'request_type' => 'tracking_sync:job',
                'order_id' => $order->id,
                'success' => false,
                'context' => [
                    'receipt_uuid' => $receipt->uuid,
                    'result' => 'api_error',
                    'message' => $exception->getMessage(),
                ],
            ]);

            return ['ok' => false, 'reason' => 'api_error', 'message' => $exception->getMessage()];
        }

        $shipment = $this->applier->normalizeShipment($remote);
        $shadow = $this->gate->isShadowMode();

        $result = $this->applier->applyGlobalTrackingShipment(
            order: $order,
            integration: $integration,
            shipment: $shipment,
            trigger: 'webhook_job',
            receiptUuid: $receipt->uuid,
            shadow: $shadow,
            updateIntegration: ! $shadow,
            updateFulfillment: ! $shadow && $this->gate->mayUpdateFulfillment(),
            allowMarkDelivered: $this->gate->mayCallMarkDelivered(),
        );

        if ($shadow) {
            ShipStationIntegrationLog::query()->create([
                'request_type' => 'tracking_sync:shadow',
                'order_id' => $order->id,
                'success' => true,
                'context' => [
                    'receipt_uuid' => $receipt->uuid,
                    'would_apply' => $result['would_apply'] ?? [],
                ],
            ]);
        }

        return $result;
    }
}