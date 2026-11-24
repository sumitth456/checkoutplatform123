<?php

namespace App\Modules\ShipStation\Services;

use App\Models\Order;
use App\Models\ShipStationIntegrationLog;
use App\Modules\ShipStation\Support\ShipStationPilotAutoStatusSyncGate;

final class ShipStationPilotAutoStatusSyncService
{
    public function __construct(
        private readonly ShipStationPilotAutoStatusSyncGate $gate,
        private readonly ShipStationConfigResolver $config,
        private readonly ShipStationV2ApiClient $v2,
        private readonly ShipStationV2RemoteShipmentSyncApplier $applier,
    ) {}

    /**
     * @param  array<string, mixed>  $webhookContext
     * @return array<string, mixed>
     */
    public function syncFromWebhook(Order $order, array $webhookContext = []): array
    {
        $eventId = (string) ($webhookContext['webhook_event_id']
            ?? $webhookContext['resource_url']
            ?? $webhookContext['ResourceUrl']
            ?? '');

        return $this->syncOrder($order, 'webhook', $eventId !== '' ? $eventId : null);
    }

    /**
     * @return array<string, mixed>
     */
    public function syncOrder(Order $order, string $trigger = 'poll', ?string $eventId = null): array
    {
        if (! $this->gate->allowsAutomaticSync($order)) {
            return ['skipped' => true, 'reason' => 'not_pilot_or_feature_disabled'];
        }

        return $this->syncOrderFromRemoteApi($order, $trigger, $eventId, enforcePilotGate: true);
    }

    /**
     * Operator-triggered sync for a single order (bypasses pilot allowlist; still GET-only).
     *
     * @return array<string, mixed>
     */
    public function syncOrderExplicit(Order $order, string $trigger = 'manual_cli', ?string $eventId = null): array
    {
        return $this->syncOrderFromRemoteApi($order, $trigger, $eventId, enforcePilotGate: false);
    }

    /**
     * @return array<string, mixed>
     */
    private function syncOrderFromRemoteApi(
        Order $order,
        string $trigger,
        ?string $eventId,
        bool $enforcePilotGate,
    ): array {
        if (! $this->config->isEnabled()) {
            return ['skipped' => true, 'reason' => 'shipstation_disabled'];
        }

        $order = $order->fresh(['shipStationIntegration', 'fulfillments']);

        if ($enforcePilotGate && $eventId !== null && $this->isDuplicateEvent($order, $eventId)) {
            return ['skipped' => true, 'reason' => 'duplicate_event', 'event_id' => $eventId];
        }

        $integration = $order->shipStationIntegration;
        $shipmentId = (string) ($integration?->external_shipment_id ?? '');

        if ($shipmentId === '') {
            return ['skipped' => true, 'reason' => 'missing_shipment_id'];
        }

        try {
            $remote = $this->v2->getShipment($shipmentId, $order->id);
        } catch (\Throwable $exception) {
            ShipStationIntegrationLog::query()->create([
                'request_type' => 'pilot_auto_status_sync:'.$trigger,
                'order_id' => $order->id,
                'shipstation_order_id' => is_numeric($order->shipstation_order_id) ? (int) $order->shipstation_order_id : null,
                'success' => false,
                'context' => [
                    'result' => 'api_error',
                    'message' => $exception->getMessage(),
                    'event_id' => $eventId,
                ],
            ]);

            return ['ok' => false, 'reason' => 'api_error', 'message' => $exception->getMessage()];
        }

        $shipment = $this->applier->normalizeShipment($remote);

        return $this->applier->applyPilotOrderShipment(
            $order,
            $integration,
            $shipment,
            $trigger,
            $eventId,
        );
    }

    private function isDuplicateEvent(Order $order, string $eventId): bool
    {
        return ShipStationIntegrationLog::query()
            ->where('order_id', $order->id)
            ->where('request_type', 'like', 'pilot_auto_status_sync:%')
            ->where('context->event_id', $eventId)
            ->exists();
    }
}