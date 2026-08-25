<?php

namespace App\Modules\Product\Import\Services;

use App\Models\Funnel;
use App\Models\Product;
use App\Models\SubscriptionPlan;
use App\Modules\Product\Enums\ProductType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class FunnelProductBulkService
{
    /**
     * @param  list<int|string>  $productIdentifiers
     * @return array{attached: int, skipped: int}
     */
    public function bulkAdd(Funnel $funnel, array $productIdentifiers, ?float $campaignPrice = null): array
    {
        $productIds = $this->resolveProductIds($productIdentifiers);
        $sortOrder = (int) $funnel->products()->max('funnel_product.sort_order');
        $attached = 0;
        $skipped = 0;

        foreach ($productIds as $productId) {
            if ($funnel->products()->where('products.id', $productId)->exists()) {
                $skipped++;

                continue;
            }

            $sortOrder++;
            $funnel->products()->attach($productId, [
                'sort_order' => $sortOrder,
                'campaign_price' => $campaignPrice,
                'is_active' => true,
            ]);
            $attached++;
        }

        return compact('attached', 'skipped');
    }

    /**
     * @param  list<int|string>  $productIdentifiers
     * @return array{detached: int}
     */
    public function bulkRemove(Funnel $funnel, array $productIdentifiers): array
    {
        $productIds = $this->resolveProductIds($productIdentifiers);
        $detached = $funnel->products()->detach($productIds);

        return ['detached' => count($detached)];
    }

    /**
     * @param  list<int|string>  $productIdentifiers
     * @return array{synced: int}
     */
    public function bulkReplace(Funnel $funnel, array $productIdentifiers): array
    {
        $productIds = $this->resolveProductIds($productIdentifiers);
        $payload = [];
        $sortOrder = 0;

        foreach ($productIds as $productId) {
            $sortOrder++;
            $payload[$productId] = [
                'sort_order' => $sortOrder,
                'is_active' => true,
            ];
        }

        $funnel->products()->sync($payload);

        return ['synced' => count($payload)];
    }

    /**
     * @param  list<array{product_id?: int, product_uuid?: string, sku?: string, campaign_price?: float|null, base_price?: float|null}>  $updates
     * @return array{updated: int}
     */
    public function bulkUpdatePrice(Funnel $funnel, array $updates): array
    {
        $updated = 0;

        DB::transaction(function () use ($funnel, $updates, &$updated): void {
            foreach ($updates as $update) {
                $product = $this->resolveSingleProduct($update);
                $campaignPrice = $update['campaign_price'] ?? null;
                $basePrice = $update['base_price'] ?? null;

                if ($campaignPrice !== null) {
                    $funnel->products()->updateExistingPivot($product->id, [
                        'campaign_price' => $this->formatMoney($campaignPrice),
                    ]);
                }

                if ($basePrice !== null) {
                    $product->update([
                        'base_price' => $this->formatMoney($basePrice),
                    ]);

                    $product->variants()->where('is_default', true)->update([
                        'price' => $this->formatMoney($basePrice),
                    ]);
                }

                $updated++;
            }
        });

        return compact('updated');
    }

    /**
     * @param  list<array{product_id?: int, product_uuid?: string, sku?: string, is_subscription?: bool, subscription_plan?: string|null, type?: string|null}>  $updates
     * @return array{updated: int}
     */
    public function bulkUpdateSubscription(Funnel $funnel, array $updates): array
    {
        unset($funnel);
        $updated = 0;

        DB::transaction(function () use ($updates, &$updated): void {
            foreach ($updates as $update) {
                $product = $this->resolveSingleProduct($update);
                $isSubscription = (bool) ($update['is_subscription'] ?? false);
                $planName = $update['subscription_plan'] ?? null;
                $type = $isSubscription ? ProductType::Subscription : ProductType::tryFrom((string) ($update['type'] ?? '')) ?? $product->type;

                $attributes = [
                    'is_subscription' => $isSubscription,
                    'type' => $type,
                    'subscription_plan_id' => null,
                ];

                if ($isSubscription && filled($planName)) {
                    $plan = SubscriptionPlan::query()
                        ->where('name', $planName)
                        ->orWhere('slug', Str::slug((string) $planName))
                        ->first();

                    $attributes['subscription_plan_id'] = $plan?->id;
                }

                $product->update($attributes);
                $updated++;
            }
        });

        return compact('updated');
    }

    /**
     * @param  list<array{product_id?: int, product_uuid?: string, sku?: string, variant_sku?: string|null, inventory?: int|null}>  $updates
     * @return array{updated: int}
     */
    public function bulkUpdateInventory(Funnel $funnel, array $updates): array
    {
        unset($funnel);
        $updated = 0;

        DB::transaction(function () use ($updates, &$updated): void {
            foreach ($updates as $update) {
                $product = $this->resolveSingleProduct($update);
                $inventory = $update['inventory'] ?? null;

                if ($inventory === null) {
                    continue;
                }

                $variantQuery = $product->variants();

                if (filled($update['variant_sku'] ?? null)) {
                    $variantQuery->where('sku', strtoupper((string) $update['variant_sku']));
                } else {
                    $variantQuery->where('is_default', true);
                }

                $variant = $variantQuery->first();

                if ($variant === null) {
                    throw new InvalidArgumentException("No variant found for product [{$product->uuid}].");
                }

                $variant->update([
                    'inventory_quantity' => max(0, (int) $inventory),
                    'track_inventory' => true,
                ]);

                $updated++;
            }
        });

        return compact('updated');
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
     * @param  array{product_id?: int, product_uuid?: string, sku?: string}  $reference
     */
    private function resolveSingleProduct(array $reference): Product
    {
        if (! empty($reference['product_id'])) {
            return Product::query()->findOrFail((int) $reference['product_id']);
        }

        if (! empty($reference['product_uuid'])) {
            return Product::query()->where('uuid', $reference['product_uuid'])->firstOrFail();
        }

        if (! empty($reference['sku'])) {
            return Product::query()->where('sku', strtoupper((string) $reference['sku']))->firstOrFail();
        }

        throw new InvalidArgumentException('Product reference must include product_id, product_uuid, or sku.');
    }

    private function formatMoney(float|string $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
