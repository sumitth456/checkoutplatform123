<?php

namespace Tests\Unit\Modules\Product;

use App\Models\Product;
use App\Modules\Product\Enums\ProductStatus;
use App\Modules\Product\Enums\ProductType;
use App\Modules\Product\Repositories\ProductRepository;
use App\Modules\Product\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProductService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ProductService(new ProductRepository);
    }

    public function test_create_generates_slug_and_subscription_flag(): void
    {
        $product = $this->service->create([
            'name' => 'Monthly Plan',
            'type' => ProductType::Subscription->value,
            'status' => ProductStatus::Draft->value,
            'billing_interval' => 'month',
            'billing_interval_count' => 1,
        ]);

        $this->assertSame('monthly-plan', $product->slug);
        $this->assertTrue($product->is_subscription);
        $this->assertSame(ProductType::Subscription, $product->type);
    }

    public function test_create_sets_published_at_when_status_is_active(): void
    {
        $product = $this->service->create([
            'name' => 'Launch Product',
            'type' => ProductType::Digital->value,
            'status' => ProductStatus::Active->value,
        ]);

        $this->assertNotNull($product->published_at);
        $this->assertFalse($product->is_subscription);
    }

    public function test_update_rejects_duplicate_slug(): void
    {
        Product::factory()->create(['slug' => 'taken-slug']);
        $product = Product::factory()->create(['slug' => 'original-slug']);

        $this->expectException(ValidationException::class);

        $this->service->update($product, ['slug' => 'taken-slug']);
    }

    public function test_restore_requires_soft_deleted_product(): void
    {
        $product = Product::factory()->create();

        $this->expectException(ValidationException::class);

        $this->service->restore($product->uuid);
    }

    public function test_restore_soft_deleted_product(): void
    {
        $product = Product::factory()->create();
        $product->delete();

        $restored = $this->service->restore($product->uuid);

        $this->assertFalse($restored->trashed());
        $this->assertSame($product->uuid, $restored->uuid);
    }

    public function test_find_throws_when_product_missing(): void
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->service->find('00000000-0000-0000-0000-000000000000');
    }
}
