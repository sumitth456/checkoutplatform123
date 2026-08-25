<?php

namespace App\Modules\Product\Import\Services;

use App\Models\Product;
use App\Models\ProductBrand;
use App\Models\ProductCategory;
use App\Models\ProductImportBatch;
use App\Models\SubscriptionPlan;
use App\Models\Variant;
use App\Modules\Product\Enums\ProductStatus;
use App\Modules\Product\Enums\ProductType;
use App\Modules\Variant\Enums\InventoryPolicy;
use App\Modules\Variant\Enums\VariantStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ProductImportExecutionService
{
    /**
     * @param  array<string, array{product: array<string, mixed>, variants: list<array<string, mixed>>}>  $groupedProducts
     * @return array{
     *     imported: int,
     *     updated: int,
     *     variants: int,
     *     skipped: int,
     *     imported_product_ids: list<int>
     * }
     */
    public function execute(ProductImportBatch $batch, array $groupedProducts): array
    {
        $stats = [
            'imported' => 0,
            'updated' => 0,
            'variants' => 0,
            'skipped' => 0,
            'imported_product_ids' => [],
        ];

        DB::transaction(function () use ($groupedProducts, &$stats): void {
            foreach ($groupedProducts as $sku => $group) {
                $productRow = $group['product'];
                $existing = Product::withTrashed()->where('sku', $sku)->first();
                $attributes = $this->mapProductAttributes($productRow, $sku, $existing);

                if ($existing !== null) {
                    if ($existing->trashed()) {
                        $existing->restore();
                    }

                    $existing->update($attributes);
                    $product = $existing->fresh();
                    $stats['updated']++;
                } else {
                    $product = Product::query()->create($attributes);
                    $stats['imported']++;
                }

                $stats['imported_product_ids'][] = $product->id;

                $variantCount = $this->syncVariants($product, $group['variants'], $productRow, $sku);
                $stats['variants'] += $variantCount;
            }
        });

        $batch->update([
            'stats' => $stats,
        ]);

        return $stats;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function mapProductAttributes(array $row, string $sku, ?Product $existing = null): array
    {
        $name = trim((string) ($row['name'] ?? 'Imported Product'));
        $type = $this->resolveProductType($row);
        $status = $this->resolveProductStatus($row);
        $isSubscription = $this->resolveIsSubscription($row, $type);
        $subscriptionPlanId = $this->resolveSubscriptionPlanId($row, $isSubscription);

        $attributes = [
            'name' => $name,
            'slug' => $existing?->slug ?? $this->uniqueSlug($name),
            'description' => $this->nullableString($row['description'] ?? null),
            'sku' => $sku,
            'type' => $type,
            'status' => $status,
            'currency' => 'USD',
            'base_price' => $this->toDecimal($row['price'] ?? 0),
            'compare_at_price' => $this->nullableDecimal($row['compare_price'] ?? null),
            'cost' => $this->nullableDecimal($row['cost'] ?? null),
            'weight' => $this->nullableDecimal($row['weight'] ?? null),
            'is_taxable' => true,
            'requires_shipping' => $type === ProductType::Physical,
            'is_subscription' => $isSubscription,
            'subscription_plan_id' => $subscriptionPlanId,
            'category_id' => $this->resolveCategoryId($row),
            'brand_id' => $this->resolveBrandId($row),
            'images' => $this->resolveImages($row),
            'metadata' => [],
        ];

        if ($status === ProductStatus::Active && $existing?->published_at === null) {
            $attributes['published_at'] = now();
        }

        if ($isSubscription && $type !== ProductType::Subscription) {
            $attributes['type'] = ProductType::Subscription;
        }

        return $attributes;
    }

    /**
     * @param  list<array<string, mixed>>  $variantRows
     * @param  array<string, mixed>  $productRow
     */
    private function syncVariants(Product $product, array $variantRows, array $productRow, string $sku): int
    {
        if ($variantRows === []) {
            $this->upsertDefaultVariant($product, $productRow, $sku);

            return 1;
        }

        $count = 0;
        $hasDefault = $product->variants()->where('is_default', true)->exists();

        foreach ($variantRows as $index => $variantRow) {
            $variantSku = $this->normalizeSku($variantRow['variant_sku'] ?? null) ?? $sku.'-'.($index + 1);
            $variantName = $this->nullableString($variantRow['variant'] ?? null) ?? 'Variant '.($index + 1);
            $price = $this->nullableDecimal($variantRow['variant_price'] ?? null)
                ?? $this->toDecimal($productRow['price'] ?? $product->base_price);
            $inventory = $this->nullableInt($variantRow['variant_inventory'] ?? null)
                ?? $this->nullableInt($productRow['inventory'] ?? null)
                ?? 0;

            $variant = Variant::withTrashed()
                ->where('product_id', $product->id)
                ->where('sku', $variantSku)
                ->first();

            $payload = [
                'name' => $variantName,
                'sku' => $variantSku,
                'currency' => $product->currency,
                'price' => $price,
                'inventory_quantity' => $inventory,
                'inventory_policy' => InventoryPolicy::Deny,
                'track_inventory' => true,
                'weight' => $product->weight,
                'is_default' => ! $hasDefault && $index === 0,
                'status' => VariantStatus::Active,
                'options' => filled($variantName) ? ['name' => $variantName] : [],
            ];

            if ($variant !== null) {
                if ($variant->trashed()) {
                    $variant->restore();
                }

                $variant->update($payload);
            } else {
                $product->variants()->create($payload);
            }

            if ($payload['is_default']) {
                $hasDefault = true;
            }

            $count++;
        }

        return $count;
    }

    /**
     * @param  array<string, mixed>  $productRow
     */
    private function upsertDefaultVariant(Product $product, array $productRow, string $sku): void
    {
        $inventory = $this->nullableInt($productRow['inventory'] ?? null) ?? 0;
        $price = $this->toDecimal($productRow['price'] ?? $product->base_price);

        $variant = Variant::withTrashed()
            ->where('product_id', $product->id)
            ->where('is_default', true)
            ->first();

        $payload = [
            'name' => 'Default',
            'sku' => $sku,
            'currency' => $product->currency,
            'price' => $price,
            'inventory_quantity' => $inventory,
            'inventory_policy' => InventoryPolicy::Deny,
            'track_inventory' => true,
            'weight' => $product->weight,
            'is_default' => true,
            'status' => VariantStatus::Active,
            'options' => [],
        ];

        if ($variant !== null) {
            if ($variant->trashed()) {
                $variant->restore();
            }

            $variant->update($payload);

            return;
        }

        $product->variants()->create($payload);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolveProductType(array $row): ProductType
    {
        $raw = strtolower(trim((string) ($row['product_type'] ?? '')));

        if ($raw !== '') {
            $type = ProductType::tryFrom($raw);

            if ($type !== null) {
                return $type;
            }
        }

        if ($this->resolveIsSubscription($row, null)) {
            return ProductType::Subscription;
        }

        return ProductType::Digital;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolveProductStatus(array $row): ProductStatus
    {
        $raw = strtolower(trim((string) ($row['status'] ?? '')));

        if ($raw !== '') {
            $status = ProductStatus::tryFrom($raw);

            if ($status !== null) {
                return $status;
            }
        }

        return ProductStatus::Draft;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolveIsSubscription(array $row, ?ProductType $type): bool
    {
        if ($type === ProductType::Subscription) {
            return true;
        }

        $raw = strtolower(trim((string) ($row['subscription'] ?? '')));

        return in_array($raw, ['1', 'true', 'yes', 'y', 'on'], true);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolveSubscriptionPlanId(array $row, bool $isSubscription): ?int
    {
        if (! $isSubscription) {
            return null;
        }

        $planName = $this->nullableString($row['subscription_plan'] ?? null);

        if ($planName === null) {
            return null;
        }

        $plan = SubscriptionPlan::query()
            ->where('name', $planName)
            ->orWhere('slug', Str::slug($planName))
            ->first();

        return $plan?->id;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolveCategoryId(array $row): ?int
    {
        $name = $this->nullableString($row['category'] ?? null);

        if ($name === null) {
            return null;
        }

        $category = ProductCategory::query()->firstOrCreate(
            ['slug' => Str::slug($name)],
            ['name' => $name],
        );

        return $category->id;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolveBrandId(array $row): ?int
    {
        $name = $this->nullableString($row['brand'] ?? null);

        if ($name === null) {
            return null;
        }

        $brand = ProductBrand::query()->firstOrCreate(
            ['slug' => Str::slug($name)],
            ['name' => $name],
        );

        return $brand->id;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    private function resolveImages(array $row): array
    {
        $url = $this->nullableString($row['image_url'] ?? null);

        if ($url === null) {
            return [];
        }

        return [$url];
    }

    private function uniqueSlug(string $name): string
    {
        $slug = Str::slug($name);
        $originalSlug = $slug;
        $counter = 1;

        while (Product::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$originalSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    private function normalizeSku(mixed $value): ?string
    {
        $string = $this->nullableString($value);

        return $string === null ? null : strtoupper($string);
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    private function toDecimal(mixed $value): string
    {
        $numeric = is_numeric($value)
            ? (float) $value
            : (float) (preg_replace('/[^0-9.\-]/', '', (string) $value) ?: 0);

        return number_format($numeric, 2, '.', '');
    }

    private function nullableDecimal(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return $this->toDecimal($value);
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return max(0, (int) round((float) $value));
    }
}
