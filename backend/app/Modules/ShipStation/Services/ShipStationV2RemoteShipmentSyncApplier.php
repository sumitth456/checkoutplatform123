<?php

namespace App\Modules\ShipStation\Services;

use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\ShipStationIntegration;
use App\Models\ShipStationIntegrationLog;
use App\Modules\Fulfillment\Services\FulfillmentOrchestratorService;
use App\Modules\Order\Enums\FulfillmentRecordStatus;
use App\Modules\ShipStation\Support\ShipStationShipmentCarrierResolver;
use App\Modules\ShipStation\Support\ShipStationShipmentSnapshot;
use App\Modules\ShipStation\Support\ShipStationV2ShipmentStatusInterpreter;
use Illuminate\Support\Arr;

/**
 * Shared GET→CRM apply logic (pilot + global tracking).
 */
final class ShipStationV2RemoteShipmentSyncApplier
{
    public function __construct(
        private readonly ShipStationIntegrationRecordService $integrationRecords,
        private readonly ShipStationV2ShipmentStatusInterpreter $interpreter,
        private readonly ShipStationShipmentCarrierResolver $carrierResolver,
        private readonly FulfillmentOrchestratorService $orchestrator,
    ) {}

    /**
     * @param  array<string, mixed>  $shipment
     * @return array<string, mixed>
     */
    public function applyPilotOrderShipment(
        Order $order,
        ?ShipStationIntegration $integration,
        array $shipment,
        string $trigger,
        ?string $eventId,
    ): array {
        return $this->applyOrderShipment(
            order: $order,
            integration: $integration,
            shipment: $shipment,
            trigger: $trigger,
            eventId: $eventId,
            logPrefix: 'pilot_auto_status_sync',
            updateIntegration: true,
            updateFulfillment: true,
            allowMarkDelivered: true,
            metaKey: 'auto_sync_meta',
        );
    }

    /**
     * @param  array<string, mixed>  $shipment
     * @return array<string, mixed>
     */
    public function applyGlobalTrackingShipment(
        Order $order,
        ?ShipStationIntegration $integration,
        array $shipment,
        string $trigger,
        ?string $receiptUuid,
        bool $shadow,
        bool $updateIntegration,
        bool $updateFulfillment,
        bool $allowMarkDelivered,
    ): array {
        if ($shadow) {
            return [
                'ok' => true,
                'shadow' => true,
                'would_apply' => ShipStationShipmentSnapshot::fromShipmentArray($shipment),
            ];
        }

        if (! $updateIntegration && ! $updateFulfillment) {
            return ['ok' => true, 'skipped' => true, 'reason' => 'no_writes_enabled'];
        }

        return $this->applyOrderShipment(
            order: $order,
            integration: $integration,
            shipment: $shipment,
            trigger: $trigger,
            eventId: $receiptUuid,
            logPrefix: 'tracking_sync',
            updateIntegration: $updateIntegration,
            updateFulfillment: $updateFulfillment,
            allowMarkDelivered: $allowMarkDelivered && $updateFulfillment,
            metaKey: 'tracking_sync_meta',
        );
    }

    /**
     * @param  array<string, mixed>  $shipment
     * @return array<string, mixed>
     */
    private function applyOrderShipment(
        Order $order,
        ?ShipStationIntegration $integration,
        array $shipment,
        string $trigger,
        ?string $eventId,
        string $logPrefix,
        bool $updateIntegration,
        bool $updateFulfillment,
        bool $allowMarkDelivered,
        string $metaKey,
    ): array {
        $remoteStatus = (string) ($shipment['shipment_status'] ?? '');
        $remoteLabel = (string) ($shipment['label_status'] ?? data_get($shipment, 'label.status', ''));
        $interpreted = $this->interpreter->interpret($remoteStatus, $remoteLabel);

        $existingRank = $metaKey === 'tracking_sync_meta'
            ? (int) data_get($integration?->tracking_sync_meta, 'status_rank', 0)
            : (int) data_get($integration?->auto_sync_meta, 'status_rank', 0);

        if ($this->interpreter->shouldDowngrade($existingRank, $interpreted['rank'])) {
            $this->recordSyncResult($order, $integration, true, 'ignored_out_of_order', $trigger, $eventId, $logPrefix, [
                'incoming_status' => $remoteStatus,
                'incoming_rank' => $interpreted['rank'],
                'existing_rank' => $existingRank,
            ]);

            return ['ok' => true, 'ignored' => true, 'reason' => 'out_of_order'];
        }

        $snapshot = ShipStationShipmentSnapshot::fromShipmentArray($shipment);
        $previousSnapshot = $metaKey === 'tracking_sync_meta'
            ? data_get($integration?->tracking_sync_meta, 'last_applied_snapshot')
            : (data_get($integration?->auto_sync_meta, 'last_applied_snapshot')
                ?? data_get($integration?->auto_sync_meta, 'remote_snapshot'));

        if (ShipStationShipmentSnapshot::isUnchanged(
            is_array($previousSnapshot) ? $previousSnapshot : null,
            $shipment,
        )) {
            $this->recordSyncResult($order, $integration, true, 'sync_skipped_unchanged', $trigger, $eventId, $logPrefix, [
                'remote_status' => $remoteStatus,
            ]);

            return ['ok' => true, 'skipped' => true, 'reason' => 'unchanged_snapshot'];
        }

        $tracking = (string) ($shipment['tracking_number'] ?? '');
        $carrierFields = $this->carrierResolver->resolveFromShipment($shipment);
        $carrierCode = $carrierFields['carrier_code']
            ?? $this->carrierResolver->sanitizeStoredCarrier($integration?->carrier);
        $carrierId = $carrierFields['carrier_id'] ?? $integration?->carrier_id;
        $service = (string) ($shipment['service_code'] ?? '');
        $shipDate = $this->interpreter->parseShipDate($shipment['ship_date'] ?? null);

        if ($updateIntegration) {
            $this->integrationRecords->upsertForOrder(
                order: $order,
                status: $interpreted['integration_status'],
                externalOrderId: $integration?->external_order_id,
                externalShipmentId: (string) ($shipment['shipment_id'] ?? $integration?->external_shipment_id),
                externalLabelId: (string) ($shipment['label_id'] ?? $integration?->external_label_id),
                trackingNumber: $tracking !== '' ? $tracking : $integration?->tracking_number,
                carrier: $carrierCode,
                carrierId: $carrierId,
                serviceCode: $service !== '' ? $service : $integration?->service_code,
                labelStatus: $interpreted['label_status'] ?? $integration?->label_status,
                responsePayload: $shipment,
                syncedAt: now(),
            );

            $integration = $order->fresh()->shipStationIntegration;
            if ($integration !== null) {
                $metaUpdate = [
                    'status_rank' => $interpreted['rank'],
                    'last_trigger' => $trigger,
                    'last_applied_snapshot' => $snapshot,
                ];
                if ($eventId !== null) {
                    $metaUpdate['last_event_id'] = $eventId;
                }

                $columnPatch = [
                    'carrier' => $carrierCode,
                    'carrier_id' => $carrierId,
                    'remote_shipment_status' => $remoteStatus !== '' ? $remoteStatus : $integration->remote_shipment_status,
                    'remote_shipped_at' => $interpreted['is_confirmed_shipped'] || $interpreted['is_delivered']
                        ? ($shipDate ?? $integration->remote_shipped_at ?? now())
                        : $integration->remote_shipped_at,
                    'last_auto_sync_at' => now(),
                    'last_auto_sync_result' => 'success',
                ];

                if ($metaKey === 'tracking_sync_meta') {
                    $columnPatch['tracking_sync_meta'] = array_merge($integration->tracking_sync_meta ?? [], $metaUpdate);
                } else {
                    $metaUpdate['remote_snapshot'] = $snapshot;
                    $columnPatch['auto_sync_meta'] = array_merge($integration->auto_sync_meta ?? [], $metaUpdate);
                }

                $integration->update($columnPatch);
            }
        }

        if ($updateFulfillment) {
            $fulfillmentCarrier = $this->carrierResolver->fulfillmentCarrierFromShipment($shipment, null);
            foreach ($order->fulfillments as $fulfillment) {
                $this->applyFulfillmentState(
                    $fulfillment,
                    $interpreted,
                    $tracking,
                    $fulfillmentCarrier ?? $this->carrierResolver->sanitizeStoredCarrier($fulfillment->carrier),
                    $service,
                    $shipDate,
                    $allowMarkDelivered,
                );
            }
        }

        $this->recordSyncResult($order, $integration, true, 'success', $trigger, $eventId, $logPrefix, [
            'remote_status' => $remoteStatus,
            'fulfillment_target' => $interpreted['fulfillment_status']?->value,
        ]);

        return [
            'ok' => true,
            'remote_status' => $remoteStatus,
            'interpreted' => $interpreted,
            'tracking' => $tracking !== '' ? $tracking : null,
        ];
    }

    private function applyFulfillmentState(
        Fulfillment $fulfillment,
        array $interpreted,
        string $tracking,
        ?string $carrier,
        string $service,
        ?\DateTimeInterface $shipDate,
        bool $allowMarkDelivered,
    ): void {
        $target = $interpreted['fulfillment_status'];

        if ($target === null) {
            $patch = [];

            if ($tracking !== '') {
                $patch['tracking_number'] = $tracking;
            }

            if ($service !== '') {
                $patch['shipping_method'] = $service;
            }

            if ($carrier !== null) {
                $patch['carrier'] = $carrier;
            } elseif ($this->carrierResolver->looksLikeShipStationResourceId((string) $fulfillment->carrier)) {
                $patch['carrier'] = null;
            }

            if ($patch !== []) {
                $fulfillment->update($patch);
            }

            return;
        }

        if ($target === FulfillmentRecordStatus::Delivered) {
            if (! $allowMarkDelivered) {
                return;
            }

            if ($tracking !== '') {
                $this->orchestrator->updateTracking($fulfillment, $tracking, $carrier, $service ?: null);
            }
            $this->orchestrator->markDelivered($fulfillment->fresh());

            return;
        }

        if ($target === FulfillmentRecordStatus::Shipped) {
            if ($tracking === '') {
                $fulfillment->update([
                    'status' => FulfillmentRecordStatus::Shipped,
                    'shipped_at' => $shipDate ?? $fulfillment->shipped_at ?? now(),
                ]);
            } else {
                $this->orchestrator->updateTracking($fulfillment, $tracking, $carrier, $service ?: null);
                if ($shipDate !== null) {
                    $fulfillment->update(['shipped_at' => $shipDate]);
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function recordSyncResult(
        Order $order,
        ?ShipStationIntegration $integration,
        bool $success,
        string $result,
        string $trigger,
        ?string $eventId,
        string $logPrefix,
        array $context = [],
    ): void {
        if ($success === false) {
            $integration?->update([
                'last_auto_sync_at' => now(),
                'last_auto_sync_result' => $result,
            ]);
        }

        ShipStationIntegrationLog::query()->create([
            'request_type' => $logPrefix.':'.$trigger,
            'order_id' => $order->id,
            'shipstation_order_id' => is_numeric($order->shipstation_order_id) ? (int) $order->shipstation_order_id : null,
            'success' => $success,
            'context' => array_merge($context, array_filter([
                'result' => $result,
                'event_id' => $eventId,
            ])),
        ]);
    }

    /**
     * @param  array<string, mixed>  $remote
     * @return array<string, mixed>
     */
    public function normalizeShipment(array $remote): array
    {
        if (isset($remote['shipments'][0]) && is_array($remote['shipments'][0])) {
            return $remote['shipments'][0];
        }

        return $remote;
    }
}