<?php

namespace App\Modules\Variant\Repositories;

use App\Models\Product;
use App\Models\Variant;
use App\Modules\Variant\Contracts\VariantRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class VariantRepository implements VariantRepositoryInterface
{
    public function paginateForProduct(Product $product, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $product->variants()->newQuery();

        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
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

    public function findByUuid(string $uuid, bool $withTrashed = false): ?Variant
    {
        $query = Variant::query();

        if ($withTrashed) {
            $query->withTrashed();
        }

        return $query->where('uuid', $uuid)->first();
    }

    public function create(Product $product, array $attributes): Variant
    {
        return $product->variants()->create($attributes);
    }

    public function update(Variant $variant, array $attributes): Variant
    {
        $variant->update($attributes);

        return $variant->fresh();
    }

    public function delete(Variant $variant): bool
    {
        return (bool) $variant->delete();
    }

    public function restore(Variant $variant): bool
    {
        return (bool) $variant->restore();
    }

    public function clearDefaultForProduct(Product $product, ?int $exceptVariantId = null): void
    {
        $query = $product->variants()->where('is_default', true);

        if ($exceptVariantId !== null) {
            $query->where('id', '!=', $exceptVariantId);
        }

        $query->update(['is_default' => false]);
    }
}
