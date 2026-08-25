<?php

namespace App\Modules\Variant\Contracts;

use App\Models\Product;
use App\Models\Variant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface VariantRepositoryInterface
{
    public function paginateForProduct(Product $product, array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function findByUuid(string $uuid, bool $withTrashed = false): ?Variant;

    public function create(Product $product, array $attributes): Variant;

    public function update(Variant $variant, array $attributes): Variant;

    public function delete(Variant $variant): bool;

    public function restore(Variant $variant): bool;

    public function clearDefaultForProduct(Product $product, ?int $exceptVariantId = null): void;
}
