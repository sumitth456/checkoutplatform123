<?php

namespace App\Modules\ShipStation\Services;

use App\Models\Fulfillment;
use App\Models\FulfillmentShipStationIntegration;
use App\Models\ShipStationIntegrationLog;
use App\Modules\Fulfillment\Services\FulfillmentOrchestratorService;
use App\Modules\Fulfillment\Services\OrderFulfillmentStatusResolver;
use App\Modules\Order\Enums\FulfillmentRecordStatus;
use App\Modules\ShipStation\Support\ShipStationShipmentCarrierResolver;
use App\Modules\ShipStation\Support\ShipStationV2ShipmentStatusInterpreter;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

/**
 * Fulfillment-scoped ShipStation status sync for reshipment shipments only.
 * Does not modify order-level shipstation_integrations or pilot auto-sync paths.
 */
final class ReshipmentWebhookSyncService
{
    public function __construct(
        private readonly ShipStationConfigResolver $config,
        private readonly ShipStationV2ApiClient $v2,
        private readonly ShipStationV2ShipmentStatusInterpreter $interpreter,
        private readonly ShipStationShipmentCarrierResolver $carrierResolver,
        private readonly FulfillmentOrchestratorService $orchestrator,
        private readonly OrderFulfillmentStatusResolver $orderFulfillmentStatus,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function tryHandleWebhook(array $payload): bool
    {
        if (! $this->config->isEnabled()) {
            return false;
        }

        $shipmentId = $this->extractShipmentId($payload);

        if ($shipmentId === null) {
            return false;
        }

        $integration = FulfillmentShipStationIntegration::query()
            ->where(function ($query) use ($shipmentId): void {
                $query->where('external_shipment_id', $shipmentId)
                    ->orWhere('external_reference', $shipmentId);
            })
            ->first();

        if ($integration === null) {
            return false;
        }

        $fulfillment = $integration->fulfillment;

        if ($fulfillment === null) {
            return false;
        }

        try {
            $remote = $this->v2->getShipment($shipmentId, $integration->order_id);
        } catch (\Throwable $exception) {
            Log::warning('Reshipment webhook sync API error', [
                'shipment_id' => $shipmentId,
                'fulfillment_id' => $fulfillment->id,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }

        $shipment = $this->normalizeShipment($remote);
        $this->applyRemoteShipment($integration, $fulfillment, $shipment, 'webhook');

        return true;
    }

    /**
     * @param  array<string, mixed>  $shipment
     */
    public function syncFulfillmentIntegration(FulfillmentShipStationIntegration $integration, string $trigger = 'manual'): bool
    {
        $shipmentId = (string) ($integration->external_shipment_id ?? '');

        if ($shipmentId === '') {
            return false;
        }

        $remote = $this->v2->getShipment($shipmentId, $integration->order_id);
        $shipment = $this->normalizeShipment($remote);
        $fulfillment = $integration->fulfillment;

        if ($fulfillment === null) {
            return false;
        }

        $this->applyRemoteShipment($integration, $fulfillment, $shipment, $trigger);

        return true;
    }

    /**
     * @param  array<string, mixed>  $shipment
     */
    private function applyRemoteShipment(
        FulfillmentShipStationIntegration $integration,
        Fulfillment $fulfillment,
        array $shipment,
        string $trigger,
    ): void {
        $remoteStatus = (string) ($shipment['shipment_status'] ?? '');
        $remoteLabel = (string) ($shipment['label_status'] ?? data_get($shipment, 'label.status', ''));
        $interpreted = $this->interpreter->interpret($remoteStatus, $remoteLabel);

        $tracking = (string) ($shipment['tracking_number'] ?? '');
        $carrierFields = $this->carrierResolver->resolveFromShipment($shipment);
        $carrierCode = $carrierFields['carrier_code']
            ?? $this->carrierResolver->sanitizeStoredCarrier($integration->carrier);
        $carrierId = $carrierFields['carrier_id'];
        $service = (string) ($shipment['service_code'] ?? '');
        $shipDate = $this->interpreter->parseShipDate($shipment['ship_date'] ?? null);

        $integration->update([
            'external_shipment_id' => (string) ($shipment['shipment_id'] ?? $integration->external_shipment_id),
            'tracking_number' => $tracking !== '' ? $tracking : $integration->tracking_number,
            'carrier' => $carrierCode,
            'service_code' => $service !== '' ? $service : $integration->service_code,
            'remote_shipment_status' => $remoteStatus !== '' ? $remoteStatus : $integration->remote_shipment_status,
            'remote_shipped_at' => $interpreted['is_confirmed_shipped'] || $interpreted['is_delivered']
                ? ($shipDate ?? $integration->remote_shipped_at ?? now())
                : $integration->remote_shipped_at,
            'last_synced_at' => now(),
            'metadata' => array_merge($integration->metadata ?? [], [
                'last_trigger' => $trigger,
                'carrier_id' => $carrierId,
                'remote_snapshot' => Arr::only($shipment, [
                    'shipment_id',
                    'shipment_status',
                    'label_status',
                    'tracking_number',
                    'carrier_code',
                    'carrier_id',
                    'service_code',
                    'ship_date',
                ]),
            ]),
        ]);

        $target = $interpreted['fulfillment_status'];

        $fulfillmentCarrier = $this->carrierResolver->fulfillmentCarrierFromShipment($shipment, $fulfillment->carrier);

        if ($target === null) {
            $patch = [];

            if ($tracking !== '') {
                $patch['tracking_number'] = $tracking;
            }

            if ($service !== '') {
                $patch['shipping_method'] = $service;
            }

            if ($fulfillmentCarrier !== null) {
                $patch['carrier'] = $fulfillmentCarrier;
            } elseif ($this->carrierResolver->looksLikeShipStationResourceId((string) $fulfillment->carrier)) {
                $patch['carrier'] = null;
            }

            if ($patch !== []) {
                $fulfillment->update($patch);
            }

            $fulfillment->update(['status' => FulfillmentRecordStatus::Processing]);

            return;
        }

        if ($target === FulfillmentRecordStatus::Delivered) {
            if ($tracking !== '') {
                $this->orchestrator->updateTracking($fulfillment, $tracking, $fulfillmentCarrier, $service ?: null);
            }
            $fulfillment->update([
                'status' => FulfillmentRecordStatus::Delivered,
                'delivered_at' => now(),
            ]);

            return;
        }

        if ($target === FulfillmentRecordStatus::Shipped) {
            if ($tracking === '') {
                $fulfillment->update([
                    'status' => FulfillmentRecordStatus::Shipped,
                    'shipped_at' => $shipDate ?? $fulfillment->shipped_at ?? now(),
                ]);
            } else {
                $this->orchestrator->updateTracking($fulfillment, $tracking, $fulfillmentCarrier, $service ?: null);
                if ($shipDate !== null) {
                    $fulfillment->update(['shipped_at' => $shipDate]);
                }
            }

            $fulfillment->update(['status' => FulfillmentRecordStatus::Reshipped]);
        }

        ShipStationIntegrationLog::query()->create([
            'request_type' => 'reshipment_status_sync:'.$trigger,
            'order_id' => $integration->order_id,
            'fulfillment_id' => $fulfillment->id,
            'shipstation_shipment_id' => (string) ($shipment['shipment_id'] ?? $integration->external_shipment_id),
            'success' => true,
            'context' => [
                'remote_status' => $remoteStatus,
                'fulfillment_status' => $target?->value,
            ],
        ]);

        if ($integration->order !== null) {
            $this->orderFulfillmentStatus->refresh($integration->order);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractShipmentId(array $payload): ?string
    {
        $resourceUrl = (string) ($payload['resource_url'] ?? $payload['ResourceUrl'] ?? '');

        if (preg_match('#/shipments/(se-[A-Za-z0-9-]+)#i', $resourceUrl, $matches)) {
            return $matches[1];
        }

        if (filled($payload['shipment_id'] ?? null)) {
            return (string) $payload['shipment_id'];
        }

        if (filled($payload['ShipmentId'] ?? null)) {
            return (string) $payload['ShipmentId'];
        }

        return null;
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