<?php

namespace App\Modules\Product\BillingCycles;

use App\Models\Product;
use App\Models\ProductBillingCycle;
use App\Models\Variant;
use App\Modules\Campaign\Enums\CampaignProductInventoryRule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ProductBillingCycleService
{
    /**
     * @return Collection<int, ProductBillingCycle>
     */
    public function list(Product $product): Collection
    {
        return $product->billingCycles()
            ->with(['billProduct', 'variant'])
            ->get();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function addCycle(Product $product, array $attributes): ProductBillingCycle
    {
        $cycleNumber = (int) ($attributes['cycle_number'] ?? ($product->billingCycles()->max('cycle_number') + 1));

        if ($cycleNumber < 1) {
            throw new InvalidArgumentException('Cycle number must be at least 1.');
        }

        if ($product->billingCycles()->where('cycle_number', $cycleNumber)->exists()) {
            throw new InvalidArgumentException("Cycle {$cycleNumber} already exists for this product.");
        }

        return $product->billingCycles()->create(
            $this->normalizeAttributes($product, $cycleNumber, $attributes),
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateCycle(ProductBillingCycle $cycle, array $attributes): ProductBillingCycle
    {
        $cycle->update($this->normalizeAttributes($cycle->product, (int) $cycle->cycle_number, $attributes, $cycle));

        return $cycle->fresh(['billProduct', 'variant']);
    }

    public function deleteCycle(ProductBillingCycle $cycle): void
    {
        $cycle->delete();
    }

    /**
     * Seed CheckoutChamp-style defaults (cycles 1–3) when a product has none.
     *
     * @return list<ProductBillingCycle>
     */
    public function ensureDefaultTemplate(Product $product): array
    {
        if ($product->billingCycles()->exists()) {
            return $product->billingCycles()->orderBy('cycle_number')->get()->all();
        }

        $product->loadMissing(['variants']);

        $defaultVariant = $product->variants->firstWhere('is_default', true)
            ?? $product->variants->first();

        $created = [];

        DB::transaction(function () use ($product, $defaultVariant, &$created): void {
            for ($cycleNumber = 1; $cycleNumber <= 3; $cycleNumber++) {
                $created[] = $product->billingCycles()->create([
                    'cycle_number' => $cycleNumber,
                    'bill_product_id' => $product->id,
                    'variant_id' => $defaultVariant?->id,
                    'price' => $product->base_price,
                    'shipping_price' => null,
                    'bill_delay_days' => $cycleNumber === 1 ? 0 : 10,
                    'delay_fulfillment' => $cycleNumber !== 1,
                    'shippable' => (bool) $product->requires_shipping,
                    'warehouse' => null,
                    'subscription_enabled' => $cycleNumber === 1 && (bool) $product->is_subscription,
                    'repeat_forever' => $cycleNumber === 3,
                    'inventory_rule' => CampaignProductInventoryRule::Inherit,
                    'inventory_quantity' => null,
                    'coupon_override' => null,
                ]);
            }
        });

        return $created;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function exportForCampaignInheritance(Product $product): array
    {
        $cycles = $this->list($product);

        if ($cycles->isEmpty()) {
            $cycles = collect($this->ensureDefaultTemplate($product));
        }

        return $cycles
            ->sortBy('cycle_number')
            ->map(fn (ProductBillingCycle $cycle): array => BillingCycleAttributeMapper::fromProductCycle($cycle))
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function syncProductCycles(Product $product, array $rows): int
    {
        $synced = 0;

        DB::transaction(function () use ($product, $rows, &$synced): void {
            $product->billingCycles()->delete();

            foreach ($rows as $index => $row) {
                $cycleNumber = (int) ($row['cycle_number'] ?? ($index + 1));
                $product->billingCycles()->create(
                    $this->normalizeAttributes($product, $cycleNumber, $row),
                );
                $synced++;
            }
        });

        return $synced;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function normalizeAttributes(
        Product $owner,
        int $cycleNumber,
        array $attributes,
        ?ProductBillingCycle $existing = null,
    ): array {
        $billProductId = (int) ($attributes['bill_product_id'] ?? $attributes['product_id'] ?? $existing?->bill_product_id ?? $owner->id);
        Product::query()->findOrFail($billProductId);

        $variantId = $attributes['variant_id'] ?? $existing?->variant_id;
        if ($variantId !== null) {
            Variant::query()->where('product_id', $billProductId)->findOrFail((int) $variantId);
        }

        $inventoryRule = $attributes['inventory_rule'] ?? $existing?->inventory_rule ?? CampaignProductInventoryRule::Inherit;
        if (is_string($inventoryRule)) {
            $inventoryRule = CampaignProductInventoryRule::from($inventoryRule);
        }

        return [
            'cycle_number' => $cycleNumber,
            'bill_product_id' => $billProductId,
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
