<?php

namespace App\Modules\Product\Services;

use App\Models\Product;
use App\Modules\Product\Contracts\ProductRepositoryInterface;
use App\Modules\Product\Enums\ProductStatus;
use App\Modules\Product\Enums\ProductType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductService
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
    ) {}

    public function list(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->products->paginate($filters, $perPage);
    }

    public function find(string $uuid, bool $withTrashed = false): Product
    {
        $product = $this->products->findByUuid($uuid, $withTrashed);

        if ($product === null) {
            throw (new ModelNotFoundException)->setModel(Product::class);
        }

        return $product;
    }

    public function create(array $data): Product
    {
        return $this->products->create($this->prepareAttributes($data));
    }

    public function update(Product $product, array $data): Product
    {
        if (isset($data['slug'])) {
            $this->assertUniqueSlug($data['slug'], $product->id);
        }

        return $this->products->update($product, $this->prepareAttributes($data, $product));
    }

    public function delete(Product $product): void
    {
        $this->products->delete($product);
    }

    public function restore(string $uuid): Product
    {
        $product = $this->find($uuid, withTrashed: true);

        if (! $product->trashed()) {
            throw ValidationException::withMessages([
                'product' => ['Product is not deleted.'],
            ]);
        }

        $this->products->restore($product);

        return $product->fresh(['variants']);
    }

    private function prepareAttributes(array $data, ?Product $existing = null): array
    {
        $type = $data['type'] ?? $existing?->type?->value ?? ProductType::Digital->value;
        $status = $data['status'] ?? $existing?->status?->value ?? ProductStatus::Draft->value;

        $data['type'] = $type;
        $data['status'] = $status;
        $data['is_subscription'] = $type === ProductType::Subscription->value;

        if ($status === ProductStatus::Active->value && blank($data['published_at'] ?? $existing?->published_at)) {
            $data['published_at'] = now();
        }

        if (blank($data['slug'] ?? null) && filled($data['name'] ?? null)) {
            $data['slug'] = Str::slug($data['name']);
        }

        if (isset($data['slug']) && $existing === null) {
            $this->assertUniqueSlug($data['slug']);
        }

        return $data;
    }

    private function assertUniqueSlug(string $slug, ?int $ignoreId = null): void
    {
        $query = Product::withTrashed()->where('slug', $slug);

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'slug' => ['The slug has already been taken.'],
            ]);
        }
    }
}
