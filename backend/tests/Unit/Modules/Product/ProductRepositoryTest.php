<?php

namespace Tests\Unit\Modules\Product;

use App\Models\Product;
use App\Modules\Product\Enums\ProductStatus;
use App\Modules\Product\Enums\ProductType;
use App\Modules\Product\Repositories\ProductRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private ProductRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new ProductRepository;
    }

    public function test_paginate_filters_by_search_status_and_type(): void
    {
        Product::factory()->create([
            'name' => 'Alpha Course',
            'status' => ProductStatus::Active,
            'type' => ProductType::Digital,
        ]);

        Product::factory()->create([
            'name' => 'Beta Bundle',
            'status' => ProductStatus::Draft,
            'type' => ProductType::Physical,
        ]);

        $results = $this->repository->paginate([
            'search' => 'Alpha',
            'status' => ProductStatus::Active->value,
            'type' => ProductType::Digital->value,
        ]);

        $this->assertCount(1, $results);
        $this->assertSame('Alpha Course', $results->first()->name);
    }

    public function test_find_by_uuid_includes_trashed_when_requested(): void
    {
        $product = Product::factory()->create();
        $product->delete();

        $this->assertNull($this->repository->findByUuid($product->uuid));
        $this->assertNotNull($this->repository->findByUuid($product->uuid, withTrashed: true));
    }

    public function test_create_update_delete_and_restore(): void
    {
        $product = $this->repository->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Repository Product',
            'slug' => 'repository-product',
            'type' => ProductType::Digital->value,
            'status' => ProductStatus::Draft->value,
            'currency' => 'USD',
        ]);

        $updated = $this->repository->update($product, ['name' => 'Updated Repository Product']);

        $this->assertSame('Updated Repository Product', $updated->name);

        $this->repository->delete($updated);

        $trashed = Product::withTrashed()->where('uuid', $product->uuid)->firstOrFail();
        $this->repository->restore($trashed);
        $this->assertDatabaseHas('products', [
            'uuid' => $product->uuid,
            'deleted_at' => null,
        ]);
    }
}
