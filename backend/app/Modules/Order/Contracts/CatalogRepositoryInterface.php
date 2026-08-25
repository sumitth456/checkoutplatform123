<?php

namespace App\Modules\Order\Contracts;

use App\Models\Product;
use App\Models\Variant;
use App\Modules\Order\DTOs\FunnelLineItemDTO;

interface CatalogRepositoryInterface
{
    public function findProductByUuid(string $uuid): ?Product;

    public function findProductByUuidAnyStatus(string $uuid): ?Product;

    public function findProductBySku(string $sku): ?Product;

    public function findVariantByUuid(string $uuid): ?Variant;

    public function findVariantBySku(string $sku): ?Variant;

    public function findVariantForProduct(Product $product, FunnelLineItemDTO $lineItem): ?Variant;
}
