<?php

namespace App\Modules\Product\Repositories;

use App\Models\Product;
use App\Modules\Product\Contracts\ProductRepositoryInterface;
use App\Modules\Product\Enums\ProductStatus;
use App\Modules\Product\Enums\ProductType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProductRepository implements ProductRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Product::query()->withCount('variants');

        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['with_trashed'])) {
            $query->withTrashed();
        }

        $sort = $filters['sort'] ?? 'created_at';
        $direction = $filters['direction'] ?? 'desc';

        return $query
            ->orderBy($sort, $direction)
            ->paginate($perPage);
    }

    public function findByUuid(string $uuid, bool $withTrashed = false): ?Product
    {
        $query = Product::query()->with('variants');

        if ($withTrashed) {
            $query->withTrashed();
        }

        return $query->where('uuid', $uuid)->first();
    }

    public function create(array $attributes): Product
    {
        return Product::query()->create($attributes);
    }

    public function update(Product $product, array $attributes): Product
    {
        $product->update($attributes);

        return $product->fresh(['variants']);
    }

    public function delete(Product $product): bool
    {
        return (bool) $product->delete();
    }

    public function restore(Product $product): bool
    {
        return (bool) $product->restore();
    }
}
