<?php

namespace App\Modules\Order\Repositories;

use App\Models\Product;
use App\Models\Variant;
use App\Modules\Order\Contracts\CatalogRepositoryInterface;
use App\Modules\Order\DTOs\FunnelLineItemDTO;
use App\Modules\Product\Enums\ProductStatus;
use App\Modules\Variant\Enums\VariantStatus;

class CatalogRepository implements CatalogRepositoryInterface
{
    public function findProductByUuid(string $uuid): ?Product
    {
        return Product::query()
            ->where('uuid', $uuid)
            ->where('status', ProductStatus::Active)
            ->first();
    }

    public function findProductByUuidAnyStatus(string $uuid): ?Product
    {
        return Product::query()
            ->where('uuid', $uuid)
            ->first();
    }

    public function findProductBySku(string $sku): ?Product
    {
        return Product::query()
            ->where('sku', $sku)
            ->where('status', ProductStatus::Active)
            ->first();
    }

    public function findVariantByUuid(string $uuid): ?Variant
    {
        return Variant::query()
            ->with('product')
            ->where('uuid', $uuid)
            ->where('status', VariantStatus::Active)
            ->first();
    }

    public function findVariantBySku(string $sku): ?Variant
    {
        return Variant::query()
            ->with('product')
            ->where('sku', $sku)
            ->where('status', VariantStatus::Active)
            ->first();
    }

    public function findVariantForProduct(Product $product, FunnelLineItemDTO $lineItem): ?Variant
    {
        $query = Variant::query()
            ->with('product')
            ->where('product_id', $product->id)
            ->where('status', VariantStatus::Active);

        if ($lineItem->size !== null) {
            $query->where(function ($builder) use ($lineItem): void {
                $builder->whereJsonContains('options->size', $lineItem->size)
                    ->orWhere('name', 'like', '%'.$lineItem->size.'%');
            });
        }

        if ($lineItem->options !== null) {
            foreach ($lineItem->options as $key => $value) {
                $query->whereJsonContains("options->{$key}", $value);
            }
        }

        $variant = $query->orderByDesc('is_default')->first();

        if ($variant !== null) {
            return $variant;
        }

        return Variant::query()
            ->with('product')
            ->where('product_id', $product->id)
            ->where('status', VariantStatus::Active)
            ->orderByDesc('is_default')
            ->first();
    }
}
