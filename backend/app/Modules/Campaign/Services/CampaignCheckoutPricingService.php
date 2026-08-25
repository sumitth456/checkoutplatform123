<?php

namespace App\Modules\Campaign\Services;

use App\Models\Campaign;
use App\Models\CampaignOrderBump;
use App\Models\CampaignProduct;
use App\Models\CampaignUpsell;
use App\Models\Variant;

final class CampaignCheckoutPricingService
{
    public function resolveUnitPrice(
        Variant $variant,
        ?Campaign $campaign = null,
        ?CampaignProduct $line = null,
        ?CampaignOrderBump $orderBump = null,
        ?CampaignUpsell $upsell = null,
    ): float {
        if ($orderBump !== null && $orderBump->bump_price !== null) {
            return (float) $orderBump->bump_price;
        }

        if ($line !== null && $line->campaign_price !== null) {
            return (float) $line->campaign_price;
        }

        $base = (float) $variant->price;

        if ($upsell !== null) {
            return $this->applyDiscount($base, $upsell->discount_type->value, $upsell->discount_value);
        }

        return $base;
    }

    public function findCampaignProductLine(Campaign $campaign, Variant $variant): ?CampaignProduct
    {
        return $campaign->campaignProducts()
            ->where('is_active', true)
            ->where('product_id', $variant->product_id)
            ->with('billingCycles.product', 'billingCycles.variant')
            ->first();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function resolveBillingProduct(CampaignProduct $line, Variant $selectedVariant): ?array
    {
        $line->loadMissing(['billingCycles.product', 'billingCycles.variant']);

        $cycle = $line->billingCycles
            ->first(fn ($row) => (int) $row->cycle_number === 1)
            ?? $line->billingCycles->first(fn ($row) => $row->variant_id === $selectedVariant->id)
            ?? $line->billingCycles->first();

        if ($cycle === null) {
            return null;
        }

        return [
            'cycle_number' => $cycle->cycle_number,
            'product_uuid' => $cycle->product?->uuid,
            'variant_uuid' => $cycle->variant?->uuid,
            'price' => $cycle->price !== null ? number_format((float) $cycle->price, 2, '.', '') : null,
            'shipping_price' => $cycle->shipping_price !== null ? number_format((float) $cycle->shipping_price, 2, '.', '') : null,
            'subscription_enabled' => (bool) $cycle->subscription_enabled,
        ];
    }

    private function applyDiscount(float $price, string $type, mixed $value): float
    {
        $amount = (float) ($value ?? 0);

        return match ($type) {
            'percentage' => max(0, round($price - ($price * ($amount / 100)), 2)),
            'fixed' => max(0, round($price - $amount, 2)),
            default => $price,
        };
    }
}
