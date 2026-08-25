<?php

namespace App\Modules\Variant\Services;

use App\Models\Product;
use App\Models\Variant;
use App\Modules\Variant\Contracts\VariantRepositoryInterface;
use App\Modules\Variant\Enums\InventoryPolicy;
use App\Modules\Variant\Enums\VariantStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VariantService
{
    public function __construct(
        private readonly VariantRepositoryInterface $variants,
    ) {}

    public function listForProduct(Product $product, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->variants->paginateForProduct($product, $filters, $perPage);
    }

    public function find(string $uuid, bool $withTrashed = false): Variant
    {
        $variant = $this->variants->findByUuid($uuid, $withTrashed);

        if ($variant === null) {
            throw (new ModelNotFoundException)->setModel(Variant::class);
        }

        return $variant;
    }

    public function create(Product $product, array $data): Variant
    {
        return DB::transaction(function () use ($product, $data): Variant {
            $attributes = $this->prepareAttributes($product, $data);

            if ($attributes['is_default'] ?? false) {
                $this->variants->clearDefaultForProduct($product);
            }

            return $this->variants->create($product, $attributes);
        });
    }

    public function update(Variant $variant, array $data): Variant
    {
        return DB::transaction(function () use ($variant, $data): Variant {
            $attributes = $this->prepareAttributes($variant->product, $data, $variant);

            if ($attributes['is_default'] ?? false) {
                $this->variants->clearDefaultForProduct($variant->product, $variant->id);
            }

            return $this->variants->update($variant, $attributes);
        });
    }

    public function delete(Variant $variant): void
    {
        $this->variants->delete($variant);
    }

    public function restore(string $uuid): Variant
    {
        $variant = $this->find($uuid, withTrashed: true);

        if (! $variant->trashed()) {
            throw ValidationException::withMessages([
                'variant' => ['Variant is not deleted.'],
            ]);
        }

        $this->variants->restore($variant);

        return $variant->fresh();
    }

    private function prepareAttributes(Product $product, array $data, ?Variant $existing = null): array
    {
        $data['currency'] = $data['currency'] ?? $existing?->currency ?? $product->currency ?? 'USD';
        $data['status'] = $data['status'] ?? $existing?->status?->value ?? VariantStatus::Active->value;
        $data['inventory_policy'] = $data['inventory_policy'] ?? $existing?->inventory_policy?->value ?? InventoryPolicy::Deny->value;
        $data['track_inventory'] = $data['track_inventory'] ?? $existing?->track_inventory ?? false;
        $data['inventory_quantity'] = $data['inventory_quantity'] ?? $existing?->inventory_quantity ?? 0;
        $data['is_default'] = $data['is_default'] ?? $existing?->is_default ?? false;

        return $data;
    }
}
