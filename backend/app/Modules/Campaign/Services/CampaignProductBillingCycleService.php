<?php

namespace App\Modules\Campaign\Services;

use App\Models\CampaignProduct;
use App\Models\CampaignProductBillingCycle;
use App\Models\Product;
use App\Modules\Campaign\Enums\CampaignProductInventoryRule;
use App\Modules\Product\BillingCycles\BillingCycleAttributeMapper;
use App\Modules\Product\BillingCycles\ProductBillingCycleService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CampaignProductBillingCycleService
{
    public function __construct(
        private readonly ProductBillingCycleService $productBillingCycles,
    ) {}

    /**
     * @return Collection<int, CampaignProductBillingCycle>
     */
    public function list(CampaignProduct $campaignProduct): Collection
    {
        return $campaignProduct->billingCycles()
            ->with(['product', 'variant'])
            ->get();
    }

    /**
     * Inherit billing cycles from the campaign subscription plan when set, otherwise catalog defaults.
     */
    public function inheritForCampaignProduct(CampaignProduct $campaignProduct): int
    {
        return app(SubscriptionPlanCampaignBillingService::class)
            ->inheritForCampaignProduct($campaignProduct);
    }

    /**
     * Copy product default cycles onto a campaign line item (override-friendly snapshot).
     */
    public function inheritFromProductDefaults(CampaignProduct $campaignProduct): int
    {
        $campaignProduct->loadMissing('product');
        $product = $campaignProduct->product;

        $rows = $this->productBillingCycles->exportForCampaignInheritance($product);

        return $this->syncCycles($campaignProduct, $rows);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function addCycle(CampaignProduct $campaignProduct, array $attributes): CampaignProductBillingCycle
    {
        $cycleNumber = (int) ($attributes['cycle_number'] ?? ($campaignProduct->billingCycles()->max('cycle_number') + 1));

        if ($cycleNumber < 1) {
            throw new InvalidArgumentException('Cycle number must be at least 1.');
        }

        if ($campaignProduct->billingCycles()->where('cycle_number', $cycleNumber)->exists()) {
            throw new InvalidArgumentException("Cycle {$cycleNumber} already exists for this campaign product.");
        }

        return $campaignProduct->billingCycles()->create(
            $this->normalizeAttributes($campaignProduct, $cycleNumber, $attributes),
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateCycle(CampaignProductBillingCycle $cycle, array $attributes): CampaignProductBillingCycle
    {
        $cycle->update($this->normalizeAttributes(
            $cycle->campaignProduct,
            (int) $cycle->cycle_number,
            $attributes,
            $cycle,
        ));

        return $cycle->fresh(['product', 'variant']);
    }

    public function deleteCycle(CampaignProductBillingCycle $cycle): void
    {
        $cycle->delete();
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function syncCycles(CampaignProduct $campaignProduct, array $rows): int
    {
        $synced = 0;

        DB::transaction(function () use ($campaignProduct, $rows, &$synced): void {
            $campaignProduct->billingCycles()->delete();

            foreach ($rows as $index => $row) {
                $cycleNumber = (int) ($row['cycle_number'] ?? ($index + 1));
                $campaignProduct->billingCycles()->create(
                    $this->normalizeAttributes($campaignProduct, $cycleNumber, $row),
                );
                $synced++;
            }
        });

        return $synced;
    }

    public function copyCycles(CampaignProduct $source, CampaignProduct $target): int
    {
        $source->loadMissing('billingCycles');
        $rows = $source->billingCycles
            ->sortBy('cycle_number')
            ->map(fn (CampaignProductBillingCycle $cycle): array => BillingCycleAttributeMapper::fromCampaignCycle($cycle))
            ->values()
            ->all();

        if ($rows === []) {
            return 0;
        }

        return $this->syncCycles($target, $rows);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function normalizeAttributes(
        CampaignProduct $campaignProduct,
        int $cycleNumber,
        array $attributes,
        ?CampaignProductBillingCycle $existing = null,
    ): array {
        $productId = (int) ($attributes['product_id'] ?? $attributes['bill_product_id'] ?? $existing?->product_id ?? $campaignProduct->product_id);
        Product::query()->findOrFail($productId);

        $variantId = $attributes['variant_id'] ?? $existing?->variant_id;
        if ($variantId !== null) {
            \App\Models\Variant::query()->where('product_id', $productId)->findOrFail((int) $variantId);
        }

        $inventoryRule = $attributes['inventory_rule'] ?? $existing?->inventory_rule ?? CampaignProductInventoryRule::Inherit;
        if (is_string($inventoryRule)) {
            $inventoryRule = CampaignProductInventoryRule::from($inventoryRule);
        }

        return [
            'cycle_number' => $cycleNumber,
            'product_id' => $productId,
            'variant_id' => $variantId,
            'price' => BillingCycleAttributeMapper::decimal($attributes['price'] ?? $existing?->price),
            'shipping_price' => BillingCycleAttributeMapper::decimal($attributes['shipping_price'] ?? $existing?->shipping_price),
            'bill_delay_days' => max(0, (int) ($attributes['bill_delay_days'] ?? $existing?->bill_delay_days ?? 10)),
            'delay_fulfillment' => (bool) ($attributes['delay_fulfillment'] ?? $existing?->delay_fulfillment ?? true),
            'shippable' => (bool) ($attributes['shippable'] ?? $existing?->shippable ?? true),
            'warehouse' => filled($attributes['warehouse'] ?? null) ? (string) $attributes['warehouse'] : ($existing?->warehouse),
            'subscription_enabled' => (bool) ($attributes['subscription_enabled'] ?? $existing?->subscription_enabled ?? ($cycleNumber === 1)),
            'repeat_forever' => (bool) ($attributes['repeat_forever'] ?? $existing?->repeat_forever ?? ($cycleNumber >= 3)),
            'inventory_rule' => $inventoryRule,
            'inventory_quantity' => isset($attributes['inventory_quantity'])
                ? max(0, (int) $attributes['inventory_quantity'])
                : $existing?->inventory_quantity,
            'coupon_override' => BillingCycleAttributeMapper::couponOverride($attributes, $existing?->coupon_override),
        ];
    }
}
