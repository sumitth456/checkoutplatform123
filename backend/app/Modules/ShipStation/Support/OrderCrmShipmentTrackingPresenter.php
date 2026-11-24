<?php

namespace App\Modules\ShipStation\Support;

use App\Models\Order;
use App\Models\ShipStationIntegration;
use App\Models\ShipStationIntegrationLog;
use App\Modules\ShipStation\Services\ShipStationIntegrationRecordService;
use Illuminate\Support\Carbon;

/**
 * Read-only CRM view model for order shipment tracking (no ShipStation writes).
 */
final class OrderCrmShipmentTrackingPresenter
{
    public function __construct(
        private readonly ShipStationIntegrationRecordService $integrationRecords,
        private readonly ShipStationDisplayStatusResolver $displayStatus,
        private readonly ShipStationShipmentCarrierResolver $carrierResolver,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function present(Order $order): array
    {
        $order->loadMissing(['shipStationIntegration', 'fulfillments']);

        $integration = $order->shipStationIntegration;
        $fulfillment = $order->fulfillments->sortByDesc('id')->first();

        if ($integration === null && $fulfillment === null) {
            return $this->emptyState('no_shipstation_integration');
        }

        $shadowSnapshot = $this->shadowSnapshot($integration);
        $shadowPreview = filled(data_get($shadowSnapshot, 'tracking_number'))
            && ! filled($integration?->tracking_number);

        $trackingNumber = filled($integration?->tracking_number)
            ? (string) $integration->tracking_number
            : (filled($fulfillment?->tracking_number) ? (string) $fulfillment->tracking_number : null);

        if ($trackingNumber === null && $shadowPreview) {
            $trackingNumber = (string) data_get($shadowSnapshot, 'tracking_number');
        }

        $carrierCode = $this->carrierResolver->sanitizeStoredCarrier($integration?->carrier)
            ?? $this->carrierResolver->sanitizeStoredCarrier($fulfillment?->carrier)
            ?? $this->carrierResolver->sanitizeStoredCarrier(data_get($shadowSnapshot, 'carrier_code'));

        $service = filled($integration?->service)
            ? (string) $integration->service
            : (filled($integration?->service_code)
                ? (string) $integration->service_code
                : ($fulfillment?->shipping_method ?? data_get($shadowSnapshot, 'service_code')));

        $statusLabel = $this->displayStatus->shipmentStatusLabel($order);
        if ($shadowPreview && filled(data_get($shadowSnapshot, 'shipment_status'))) {
            $statusLabel .= ' (shadow preview)';
        }

        $trackingUrl = $this->integrationRecords->resolveTrackingUrl($order);

        $labelCreatedAt = $this->resolveLabelCreatedAt($integration, $fulfillment);
        $shippedAt = $integration?->remote_shipped_at ?? $fulfillment?->shipped_at;
        $deliveredAt = $fulfillment?->delivered_at;

        if ($shippedAt === null && filled(data_get($shadowSnapshot, 'ship_date'))) {
            $shippedAt = $this->parseNullableDate((string) data_get($shadowSnapshot, 'ship_date'));
        }

        $lastSynced = $this->resolveLastSynchronizedAt($integration, $order);

        return [
            'state' => $this->resolveUiState($trackingNumber, $integration, $shadowPreview),
            'tracking_number' => $trackingNumber,
            'tracking_number_copyable' => filled($trackingNumber),
            'shadow_preview' => $shadowPreview,
            'carrier' => $this->integrationRecords->displayCarrierName($order) ?? $this->displayCarrierFromCode($carrierCode),
            'shipping_method' => filled($service) ? (string) $service : null,
            'status_label' => $statusLabel,
            'status_subtitle' => $this->displayStatus->shipmentStatusSubtitle($order),
            'external_shipment_id' => $integration?->external_shipment_id,
            'label_created_at' => $this->formatDateTime($labelCreatedAt),
            'shipped_at' => $this->formatDateTime($shippedAt),
            'delivered_at' => $this->formatDateTime($deliveredAt),
            'last_synchronized_at' => $this->formatDateTime($lastSynced),
            'tracking_url' => $trackingUrl,
            'tracking_url_trusted' => $this->isTrustedTrackingUrl($trackingUrl),
            'sync_mechanism' => $this->syncMechanismDescription(),
            'expected_latency' => $this->expectedUpdateLatency(),
            'history' => $this->buildHistory($integration, $order),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyState(string $reason): array
    {
        return [
            'state' => 'empty',
            'reason' => $reason,
            'tracking_number' => null,
            'tracking_number_copyable' => false,
            'shadow_preview' => false,
            'carrier' => null,
            'shipping_method' => null,
            'status_label' => 'Pending',
            'status_subtitle' => 'No ShipStation shipment linked to this order yet.',
            'external_shipment_id' => null,
            'label_created_at' => null,
            'shipped_at' => null,
            'delivered_at' => null,
            'last_synchronized_at' => null,
            'tracking_url' => null,
            'tracking_url_trusted' => false,
            'sync_mechanism' => $this->syncMechanismDescription(),
            'expected_latency' => $this->expectedUpdateLatency(),
            'history' => [],
        ];
    }

  /**
     * @return array<string, mixed>|null
     */
    private function shadowSnapshot(?ShipStationIntegration $integration): ?array
    {
        $fromMeta = data_get($integration?->tracking_sync_meta, 'shadow_last_snapshot');

        if (is_array($fromMeta) && $fromMeta !== []) {
            return $fromMeta;
        }

        return null;
    }

    private function resolveUiState(?string $trackingNumber, ?ShipStationIntegration $integration, bool $shadowPreview): string
    {
        if ($shadowPreview) {
            return 'shadow_preview';
        }

        if (filled($trackingNumber)) {
            return 'ready';
        }

        if ($integration !== null && filled($integration->external_shipment_id)) {
            return 'pending';
        }

        return 'not_available';
    }

    private function resolveLabelCreatedAt(?ShipStationIntegration $integration, $fulfillment): ?Carbon
    {
        foreach ([
            data_get($integration?->response_payload, 'label_created_at'),
            data_get($integration?->response_payload, 'label.createDate'),
            data_get($integration?->response_payload, 'createDate'),
            $integration?->synced_at,
            $fulfillment?->packed_at,
        ] as $candidate) {
            $parsed = $this->parseNullableDate($candidate);

            if ($parsed !== null) {
                return $parsed;
            }
        }

        return null;
    }

    private function resolveLastSynchronizedAt(?ShipStationIntegration $integration, Order $order): ?Carbon
    {
        $candidates = array_filter([
            $this->parseNullableDate(data_get($integration?->tracking_sync_meta, 'shadow_last_at')),
            $integration?->last_auto_sync_at,
            $integration?->synced_at,
        ]);

        if ($candidates !== []) {
            return collect($candidates)->sortDesc()->first();
        }

        $log = ShipStationIntegrationLog::query()
            ->where('order_id', $order->id)
            ->whereIn('request_type', [
                'tracking_sync:shadow',
                'tracking_sync:webhook_job',
                'tracking_sync:success',
            ])
            ->latest('id')
            ->first();

        return $log?->created_at;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildHistory(?ShipStationIntegration $integration, Order $order): array
    {
        $events = [];

        foreach (data_get($integration?->tracking_sync_meta, 'sync_history', []) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $events[] = [
                'at' => $row['at'] ?? null,
                'status' => $row['shipment_status'] ?? $row['status'] ?? null,
                'description' => $row['description'] ?? 'Tracking sync snapshot',
                'location' => $row['location'] ?? null,
                'delivery' => $row['delivery'] ?? null,
                'source' => $row['source'] ?? 'sync',
            ];
        }

        $payload = $integration?->response_payload;

        if (is_array($payload)) {
            foreach ($this->extractCarrierEvents($payload) as $event) {
                $events[] = $event;
            }
        }

        $events = collect($events)
            ->filter(fn (array $e): bool => filled($e['at'] ?? null) || filled($e['description'] ?? null))
            ->sortByDesc(fn (array $e) => $e['at'] ?? '')
            ->values()
            ->all();

        if ($events === []) {
            $latestLog = ShipStationIntegrationLog::query()
                ->where('order_id', $order->id)
                ->where('request_type', 'like', 'tracking_sync:%')
                ->latest('id')
                ->limit(5)
                ->get();

            foreach ($latestLog as $log) {
                $events[] = [
                    'at' => $log->created_at?->toIso8601String(),
                    'status' => data_get($log->context, 'result') ?? ($log->success ? 'logged' : 'error'),
                    'description' => 'ShipStation sync log: '.$log->request_type,
                    'location' => null,
                    'delivery' => null,
                    'source' => 'integration_log',
                ];
            }
        }

        return $events;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function extractCarrierEvents(array $payload): array
    {
        $raw = $payload['events'] ?? $payload['tracking_events'] ?? data_get($payload, 'tracking.events');

        if (! is_array($raw)) {
            return [];
        }

        $events = [];

        foreach ($raw as $item) {
            if (! is_array($item)) {
                continue;
            }

            $events[] = [
                'at' => $item['occurred_at'] ?? $item['event_date'] ?? $item['date'] ?? $item['timestamp'] ?? null,
                'status' => $item['status'] ?? $item['carrier_status'] ?? $item['status_code'] ?? null,
                'description' => $item['description'] ?? $item['status_description'] ?? $item['message'] ?? null,
                'location' => $item['city'] ?? data_get($item, 'location.city') ?? $item['location'] ?? null,
                'delivery' => data_get($item, 'delivery_information') ?? data_get($item, 'delivery'),
                'source' => 'carrier_payload',
            ];
        }

        return $events;
    }

    private function displayCarrierFromCode(?string $code): ?string
    {
        if ($code === null) {
            return null;
        }

        return $this->carrierResolver->displayNameForCode($code);
    }

    private function parseNullableDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function formatDateTime(mixed $value): ?string
    {
        $parsed = $value instanceof Carbon ? $value : $this->parseNullableDate($value);

        return $parsed?->timezone(config('app.timezone'))->format('Y-m-d H:i:s T');
    }

    private function isTrustedTrackingUrl(?string $url): bool
    {
        if (! filled($url)) {
            return false;
        }

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        return str_starts_with(strtolower($url), 'https://');
    }

    private function syncMechanismDescription(): string
    {
        $trackingEnabled = (bool) config('shipstation.tracking_sync.enabled', false);
        $polling = (bool) data_get(config('shipstation.pilot_auto_status_sync'), 'polling_enabled', false);

        if ($trackingEnabled) {
            return 'Webhook-triggered queue job (SHIP_NOTIFY) → GET shipment (read-only) → CRM update';
        }

        if ($polling) {
            return 'Pilot status polling (limited UUIDs) — not global real-time';
        }

        return 'Label purchase / manual ShipStation sync writes integration rows; global tracking webhooks disabled';
    }

    private function expectedUpdateLatency(): string
    {
        $trackingEnabled = (bool) config('shipstation.tracking_sync.enabled', false);

        if ($trackingEnabled) {
            return 'Typically seconds to a few minutes after ShipStation delivers the webhook and the queue worker processes SyncShipStationTrackingJob. Not instantaneous.';
        }

        if ((bool) data_get(config('shipstation.pilot_auto_status_sync'), 'polling_enabled', false)) {
            $minutes = (int) data_get(config('shipstation.pilot_auto_status_sync'), 'polling_minutes', 15);

            return "Pilot polling only — up to {$minutes} minutes between checks (pilot UUIDs).";
        }

        return 'Updates when warehouse label/shipment data is written during fulfillment submission; no global webhook sync while disabled.';
    }
}