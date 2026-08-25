<?php

namespace App\Modules\Campaign\Services;

use App\Models\Campaign;
use App\Models\CampaignProduct;
use App\Models\Product;
use App\Models\Variant;
use App\Modules\Campaign\Enums\CampaignProductInventoryRule;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CampaignProductBulkService
{
    public function __construct(
        private readonly CampaignProductBillingCycleService $billingCycles,
    ) {}

    /**
     * @param  list<int|string>  $productIdentifiers
     * @param  array<string, mixed>  $defaults
     * @return array{attached: int, skipped: int}
     */
    public function bulkAdd(Campaign $campaign, array $productIdentifiers, array $defaults = []): array
    {
        $productIds = $this->resolveProductIds($productIdentifiers);
        $sortOrder = (int) $campaign->campaignProducts()->max('sort_order');
        $attached = 0;
        $skipped = 0;
        $defaults = $this->normalizeDefaults($defaults);

        foreach ($productIds as $productId) {
            if ($campaign->campaignProducts()->where('product_id', $productId)->exists()) {
                $skipped++;

                continue;
            }

            $sortOrder++;
            /** @var CampaignProduct $campaignProduct */
            $campaignProduct = $campaign->campaignProducts()->create([
                'product_id' => $productId,
                'sort_order' => $sortOrder,
                ...$defaults,
            ]);

            $this->billingCycles->inheritForCampaignProduct($campaignProduct);
            $attached++;
        }

        return compact('attached', 'skipped');
    }

    /**
     * @param  list<int|string>  $productIdentifiers
     * @return array{detached: int}
     */
    public function bulkRemove(Campaign $campaign, array $productIdentifiers): array
    {
        $productIds = $this->resolveProductIds($productIdentifiers);
        $detached = $campaign->campaignProducts()
            ->whereIn('product_id', $productIds)
            ->delete();

        return ['detached' => $detached];
    }

    /**
     * @param  list<int|string>  $productIdentifiers
     * @return array{synced: int}
     */
    public function bulkReplace(Campaign $campaign, array $productIdentifiers): array
    {
        $productIds = $this->resolveProductIds($productIdentifiers);

        return DB::transaction(function () use ($campaign, $productIds): array {
            $campaign->campaignProducts()
                ->whereNotIn('product_id', $productIds)
                ->delete();

            $existing = $campaign->campaignProducts()
                ->whereIn('product_id', $productIds)
                ->pluck('product_id')
                ->all();

            $missing = array_values(array_diff($productIds, $existing));

            if ($missing !== []) {
                $this->bulkAdd($campaign, $missing);
            }

            $sortOrder = 0;
            foreach ($productIds as $productId) {
                $sortOrder++;
                $campaign->campaignProducts()
                    ->where('product_id', $productId)
                    ->update(['sort_order' => $sortOrder]);
            }

            return ['synced' => count($productIds)];
        });
    }

    /**
     * @param  list<array{product_id: int, sort_order?: int|null}>  $order
     * @return array{updated: int}
     */
    public function updateSortOrder(Campaign $campaign, array $order): array
    {
        $updated = 0;

        DB::transaction(function () use ($campaign, $order, &$updated): void {
            foreach ($order as $row) {
                $productId = (int) ($row['product_id'] ?? 0);

                if ($productId < 1) {
                    continue;
                }

                $updated += $campaign->campaignProducts()
                    ->where('product_id', $productId)
                    ->update(['sort_order' => (int) ($row['sort_order'] ?? 0)]);
            }
        });

        return compact('updated');
    }

    /**
     * @param  list<array{product_id: int, priority: int}>  $updates
     * @return array{updated: int}
     */
    public function updatePriority(Campaign $campaign, array $updates): array
    {
        $updated = 0;

        DB::transaction(function () use ($campaign, $updates, &$updated): void {
            foreach ($updates as $update) {
                $productId = (int) ($update['product_id'] ?? 0);

                if ($productId < 1) {
                    continue;
                }

                $updated += $campaign->campaignProducts()
                    ->where('product_id', $productId)
                    ->update(['priority' => (int) $update['priority']]);
            }
        });

        return compact('updated');
    }

    /**
     * @param  list<array<string, mixed>>  $updates
     * @return array{updated: int}
     */
    public function bulkUpdateRules(Campaign $campaign, array $updates): array
    {
        $updated = 0;

        DB::transaction(function () use ($campaign, $updates, &$updated): void {
            foreach ($updates as $update) {
                $productId = (int) ($update['product_id'] ?? 0);

                if ($productId < 1) {
                    continue;
                }

                $attributes = [];

                if (array_key_exists('min_quantity', $update)) {
                    $attributes['min_quantity'] = $update['min_quantity'] !== null
                        ? (int) $update['min_quantity']
                        : null;
                }

                if (array_key_exists('max_quantity', $update)) {
                    $attributes['max_quantity'] = $update['max_quantity'] !== null
                        ? (int) $update['max_quantity']
                        : null;
                }

                if (array_key_exists('inventory_rule', $update) && $update['inventory_rule'] !== null) {
                    $attributes['inventory_rule'] = $this->resolveInventoryRule($update['inventory_rule']);
                }

                if (array_key_exists('campaign_price', $update)) {
                    $attributes['campaign_price'] = $update['campaign_price'] !== null
                        ? $this->formatMoney($update['campaign_price'])
                        : null;
                }

                if (array_key_exists('variant_settings', $update)) {
                    $attributes['variant_settings'] = $update['variant_settings'];
                }

                if (array_key_exists('is_active', $update)) {
                    $attributes['is_active'] = (bool) $update['is_active'];
                }

                if ($attributes === []) {
                    continue;
                }

                $updated += $campaign->campaignProducts()
                    ->where('product_id', $productId)
                    ->update($attributes);
            }
        });

        return compact('updated');
    }

    public function copyProducts(Campaign $source, Campaign $target): void
    {
        $source->load(['campaignProducts.billingCycles']);

        DB::transaction(function () use ($source, $target): void {
            foreach ($source->campaignProducts as $row) {
                /** @var CampaignProduct $copy */
                $copy = $target->campaignProducts()->create([
                    'product_id' => $row->product_id,
                    'variant_id' => $row->variant_id,
                    'quantity' => $row->quantity,
                    'sku_override' => $row->sku_override,
                    'priority' => $row->priority,
                    'sort_order' => $row->sort_order,
                    'campaign_price' => $row->campaign_price,
                    'min_quantity' => $row->min_quantity,
                    'max_quantity' => $row->max_quantity,
                    'inventory_rule' => $row->inventory_rule,
                    'variant_settings' => $row->variant_settings,
                    'is_active' => $row->is_active,
                ]);

                foreach ($row->billingCycles as $cycle) {
                    $copy->billingCycles()->create(
                        collect($cycle->getAttributes())
                            ->except(['id', 'campaign_product_id', 'created_at', 'updated_at'])
                            ->all()
                    );
                }
            }
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function initialVariantSettings(Product $product): array
    {
        $product->loadMissing('variants');

        return $this->buildVariantSettings($product);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildVariantSettings(Product $product): array
    {
        return $product->variants
            ->map(fn (Variant $variant): array => [
                'variant_id' => $variant->id,
                'sku' => $variant->sku,
                'enabled' => true,
                'price_override' => null,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<int|string>  $identifiers
     * @return list<int>
     */
    private function resolveProductIds(array $identifiers): array
    {
        $ids = [];

        foreach ($identifiers as $identifier) {
            if (is_int($identifier)) {
                $ids[] = $identifier;

                continue;
            }

            $string = trim((string) $identifier);

            if ($string === '') {
                continue;
            }

            if (ctype_digit($string)) {
                $ids[] = (int) $string;

                continue;
            }

            $product = Product::query()
                ->where('uuid', $string)
                ->orWhere('sku', strtoupper($string))
                ->first();

            if ($product !== null) {
                $ids[] = $product->id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    private function normalizeDefaults(array $defaults): array
    {
        $normalized = [
            'priority' => (int) ($defaults['priority'] ?? 100),
            'quantity' => (int) ($defaults['quantity'] ?? 1),
            'is_active' => (bool) ($defaults['is_active'] ?? true),
            'inventory_rule' => CampaignProductInventoryRule::Inherit,
        ];

        if (array_key_exists('campaign_price', $defaults) && $defaults['campaign_price'] !== null) {
            $normalized['campaign_price'] = $this->formatMoney($defaults['campaign_price']);
        }

        if (array_key_exists('min_quantity', $defaults)) {
            $normalized['min_quantity'] = $defaults['min_quantity'] !== null
                ? (int) $defaults['min_quantity']
                : null;
        }

        if (array_key_exists('max_quantity', $defaults)) {
            $normalized['max_quantity'] = $defaults['max_quantity'] !== null
                ? (int) $defaults['max_quantity']
                : null;
        }

        if (array_key_exists('inventory_rule', $defaults) && $defaults['inventory_rule'] !== null) {
            $normalized['inventory_rule'] = $this->resolveInventoryRule($defaults['inventory_rule']);
        }

        if (array_key_exists('variant_id', $defaults)) {
            $normalized['variant_id'] = $defaults['variant_id'] !== null
                ? (int) $defaults['variant_id']
                : null;
        }

        if (array_key_exists('sku_override', $defaults)) {
            $normalized['sku_override'] = $defaults['sku_override'];
        }

        return $normalized;
    }

    private function resolveInventoryRule(mixed $value): CampaignProductInventoryRule
    {
        if ($value instanceof CampaignProductInventoryRule) {
            return $value;
        }

        $rule = CampaignProductInventoryRule::tryFrom((string) $value);

        if ($rule === null) {
            throw new InvalidArgumentException("Unknown inventory rule [{$value}].");
        }

        return $rule;
    }

    private function formatMoney(float|string $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
