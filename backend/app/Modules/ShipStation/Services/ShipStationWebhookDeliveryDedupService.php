<?php

namespace App\Modules\ShipStation\Services;

use App\Models\ShipStationWebhookReceipt;
use Illuminate\Support\Facades\Cache;

final class ShipStationWebhookDeliveryDedupService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function buildFingerprint(array $payload): string
    {
        $stableId = $this->extractStableDeliveryId($payload);

        if ($stableId !== null) {
            return hash('sha256', 'stable:'.$stableId);
        }

        $canonical = json_encode($this->canonicalizePayload($payload));

        return hash('sha256', 'body:'.($canonical !== false ? $canonical : ''));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function isStableIdDuplicateAlreadyProcessed(string $fingerprint, array $payload): bool
    {
        if ($this->extractStableDeliveryId($payload) === null) {
            return false;
        }

        return ShipStationWebhookReceipt::query()
            ->where('delivery_fingerprint', $fingerprint)
            ->whereIn('status', [
                ShipStationWebhookReceipt::STATUS_RECEIVED,
                ShipStationWebhookReceipt::STATUS_DISPATCHED,
                ShipStationWebhookReceipt::STATUS_PROCESSING,
                ShipStationWebhookReceipt::STATUS_PROCESSED,
            ])
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function isShortWindowBodyDuplicate(string $fingerprint, array $payload): bool
    {
        if ($this->extractStableDeliveryId($payload) !== null) {
            return false;
        }

        return Cache::has($this->cacheKey($fingerprint));
    }

    public function rememberShortWindow(string $fingerprint): void
    {
        $ttl = (int) config('shipstation.tracking_sync.delivery_dedup_ttl_seconds', 120);
        Cache::put($this->cacheKey($fingerprint), true, $ttl);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractStableDeliveryId(array $payload): ?string
    {
        foreach (['notification_id', 'webhook_id', 'event_id', 'id'] as $key) {
            if (filled($payload[$key] ?? null)) {
                return strtolower($key).':'.(string) $payload[$key];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function canonicalizePayload(array $payload): array
    {
        ksort($payload);

        return $payload;
    }

    private function cacheKey(string $fingerprint): string
    {
        return 'shipstation:webhook:dedup:'.$fingerprint;
    }
}