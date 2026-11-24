<?php

namespace App\Modules\ShipStation\Services;

use App\Models\Order;
use App\Modules\LandingPage\Services\TshirtLandingPageSettingsService;
use Illuminate\Support\Carbon;

/**
 * Gates automatic ShipStation label purchase and CRM shipped/in-transit completion
 * for FamTeesWorld t-shirt funnel orders created on or after a UTC cutoff.
 *
 * Does not affect order sync (V2 shipment create), manual admin fulfillment, or PayPal.
 */
class ShipStationFamTeesDeferAutomaticShippingService
{
    public function __construct(
        private readonly TshirtLandingPageSettingsService $tshirtLandingPages,
    ) {}

    public function cutoffTimestamp(): ?Carbon
    {
        $value = config('shipstation.famtees_defer_auto_shipment_at');

        if (blank($value)) {
            return null;
        }

        return Carbon::parse((string) $value)->utc();
    }

    public function isFeatureConfigured(): bool
    {
        return $this->cutoffTimestamp() !== null;
    }

    public function isFamTeesWorldFunnelOrder(Order $order): bool
    {
        $host = $this->hostFromLandingPageUrl($order->landing_page_url);

        if ($host === null) {
            return false;
        }

        return $this->tshirtLandingPages->isTshirtHostname($host);
    }

    public function isOrderCreatedOnOrAfterCutoff(Order $order): bool
    {
        $cutoff = $this->cutoffTimestamp();

        if ($cutoff === null) {
            return false;
        }

        $createdAt = $order->created_at?->copy()->utc();

        if ($createdAt === null) {
            return false;
        }

        return $createdAt->greaterThanOrEqualTo($cutoff);
    }

    /**
     * When true, automatic label scheduling, label purchase, and auto shipped completion must not run.
     */
    public function blocksAutomaticShipping(Order $order): bool
    {
        if (! $this->isFeatureConfigured()) {
            return false;
        }

        if (! $this->isFamTeesWorldFunnelOrder($order)) {
            return false;
        }

        return $this->isOrderCreatedOnOrAfterCutoff($order);
    }

    private function hostFromLandingPageUrl(?string $url): ?string
    {
        if (! filled($url)) {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : null;
    }
}
