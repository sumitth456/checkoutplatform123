<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Modules\Product\Enums\ProductStatus;
use App\Modules\Product\Enums\ProductType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_products(): void
    {
        Product::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/products');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['uuid', 'name', 'slug', 'type', 'status'],
                ],
                'links',
                'meta',
            ])
            ->assertJsonCount(3, 'data');
    }

    public function test_can_filter_products_by_status(): void
    {
        Product::factory()->create(['status' => ProductStatus::Draft]);
        Product::factory()->active()->create();

        $response = $this->getJson('/api/v1/products?status=active');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'active');
    }

    public function test_can_filter_products_by_type_and_search(): void
    {
        Product::factory()->create([
            'name' => 'Premium Checkout',
            'type' => ProductType::Digital,
        ]);

        Product::factory()->create([
            'name' => 'Shipping Box',
            'type' => ProductType::Physical,
        ]);

        $response = $this->getJson('/api/v1/products?type=digital&search=Premium');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Premium Checkout');
    }

    public function test_list_request_validates_query_parameters(): void
    {
        $response = $this->getJson('/api/v1/products?status=invalid&per_page=500');

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['status', 'per_page']);
    }

    public function test_can_create_product(): void
    {
        $response = $this->postJson('/api/v1/products', [
            'name' => 'Checkout Pro',
            'type' => ProductType::Digital->value,
            'status' => ProductStatus::Active->value,
            'currency' => 'USD',
            'base_price' => 99.00,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Checkout Pro')
            ->assertJsonPath('data.type', 'digital')
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('products', [
            'name' => 'Checkout Pro',
            'type' => ProductType::Digital->value,
        ]);
    }

    public function test_can_create_subscription_product(): void
    {
        $response = $this->postJson('/api/v1/products', [
            'name' => 'Pro Subscription',
            'type' => ProductType::Subscription->value,
            'billing_interval' => 'month',
            'billing_interval_count' => 1,
            'trial_days' => 14,
            'base_price' => 49.00,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', 'subscription')
            ->assertJsonPath('data.is_subscription', true)
            ->assertJsonPath('data.billing_interval', 'month');
    }

    public function test_can_show_product_by_uuid(): void
    {
        $product = Product::factory()->create();

        $response = $this->getJson("/api/v1/products/{$product->uuid}");

        $response->assertOk()
            ->assertJsonPath('data.uuid', $product->uuid)
            ->assertJsonPath('data.name', $product->name);
    }

    public function test_show_returns_not_found_for_missing_product(): void
    {
        $response = $this->getJson('/api/v1/products/00000000-0000-0000-0000-000000000000');

        $response->assertNotFound();
    }

    public function test_can_update_product(): void
    {
        $product = Product::factory()->create(['name' => 'Old Name']);

        $response = $this->patchJson("/api/v1/products/{$product->uuid}", [
            'name' => 'Updated Name',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Name');

        $this->assertDatabaseHas('products', [
            'uuid' => $product->uuid,
            'name' => 'Updated Name',
        ]);
    }

    public function test_update_rejects_duplicate_slug(): void
    {
        Product::factory()->create(['slug' => 'existing-slug']);
        $product = Product::factory()->create(['slug' => 'mine']);

        $response = $this->patchJson("/api/v1/products/{$product->uuid}", [
            'slug' => 'existing-slug',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_can_soft_delete_and_restore_product(): void
    {
        $product = Product::factory()->create();

        $this->deleteJson("/api/v1/products/{$product->uuid}")
            ->assertNoContent();

        $this->assertSoftDeleted('products', ['uuid' => $product->uuid]);

        $this->postJson("/api/v1/products/{$product->uuid}/restore")
            ->assertOk()
            ->assertJsonPath('data.uuid', $product->uuid);

        $this->assertDatabaseHas('products', [
            'uuid' => $product->uuid,
            'deleted_at' => null,
        ]);
    }

    public function test_restore_non_deleted_product_returns_validation_error(): void
    {
        $product = Product::factory()->create();

        $response = $this->postJson("/api/v1/products/{$product->uuid}/restore");

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['product']);
    }

    public function test_create_product_validation_fails_without_name(): void
    {
        $response = $this->postJson('/api/v1/products', [
            'type' => ProductType::Digital->value,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_create_subscription_requires_billing_fields(): void
    {
        $response = $this->postJson('/api/v1/products', [
            'name' => 'Incomplete Subscription',
            'type' => ProductType::Subscription->value,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['billing_interval', 'billing_interval_count']);
    }
}
