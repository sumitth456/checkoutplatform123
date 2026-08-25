<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\Variant;
use App\Modules\Variant\Enums\VariantStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VariantApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_variants_for_product(): void
    {
        $product = Product::factory()->create();
        Variant::factory()->count(2)->create(['product_id' => $product->id]);
        Variant::factory()->create();

        $response = $this->getJson("/api/v1/products/{$product->uuid}/variants");

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['uuid', 'sku', 'price', 'status'],
                ],
            ]);
    }

    public function test_can_create_variant_for_product(): void
    {
        $product = Product::factory()->create(['currency' => 'USD']);

        $response = $this->postJson("/api/v1/products/{$product->uuid}/variants", [
            'sku' => 'VAR-001',
            'price' => 49.99,
            'name' => 'Standard',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.sku', 'VAR-001')
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.price', '49.99');

        $this->assertDatabaseHas('variants', [
            'product_id' => $product->id,
            'sku' => 'VAR-001',
        ]);
    }

    public function test_can_show_variant_scoped_to_product(): void
    {
        $product = Product::factory()->create();
        $variant = Variant::factory()->create(['product_id' => $product->id]);

        $response = $this->getJson("/api/v1/products/{$product->uuid}/variants/{$variant->uuid}");

        $response->assertOk()
            ->assertJsonPath('data.uuid', $variant->uuid);
    }

    public function test_cannot_access_variant_from_wrong_product(): void
    {
        $product = Product::factory()->create();
        $otherProduct = Product::factory()->create();
        $variant = Variant::factory()->create(['product_id' => $otherProduct->id]);

        $this->getJson("/api/v1/products/{$product->uuid}/variants/{$variant->uuid}")
            ->assertNotFound();
    }

    public function test_can_update_variant(): void
    {
        $product = Product::factory()->create();
        $variant = Variant::factory()->create([
            'product_id' => $product->id,
            'price' => 10.00,
        ]);

        $response = $this->patchJson("/api/v1/products/{$product->uuid}/variants/{$variant->uuid}", [
            'price' => 15.00,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.price', '15.00');
    }

    public function test_setting_default_variant_unsets_previous_default(): void
    {
        $product = Product::factory()->create();
        $existingDefault = Variant::factory()->default()->create(['product_id' => $product->id]);
        $variant = Variant::factory()->create(['product_id' => $product->id]);

        $this->patchJson("/api/v1/products/{$product->uuid}/variants/{$variant->uuid}", [
            'is_default' => true,
        ])->assertOk();

        $this->assertFalse($existingDefault->fresh()->is_default);
        $this->assertTrue($variant->fresh()->is_default);
    }

    public function test_can_soft_delete_and_restore_variant(): void
    {
        $product = Product::factory()->create();
        $variant = Variant::factory()->create(['product_id' => $product->id]);

        $this->deleteJson("/api/v1/products/{$product->uuid}/variants/{$variant->uuid}")
            ->assertNoContent();

        $this->assertSoftDeleted('variants', ['uuid' => $variant->uuid]);

        $this->postJson("/api/v1/products/{$product->uuid}/variants/{$variant->uuid}/restore")
            ->assertOk()
            ->assertJsonPath('data.uuid', $variant->uuid);
    }

    public function test_create_variant_requires_sku_and_price(): void
    {
        $product = Product::factory()->create();

        $response = $this->postJson("/api/v1/products/{$product->uuid}/variants", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['sku', 'price']);
    }

    public function test_can_filter_variants_by_status(): void
    {
        $product = Product::factory()->create();
        Variant::factory()->create([
            'product_id' => $product->id,
            'status' => VariantStatus::Active,
        ]);
        Variant::factory()->create([
            'product_id' => $product->id,
            'status' => VariantStatus::Inactive,
        ]);

        $response = $this->getJson("/api/v1/products/{$product->uuid}/variants?status=active");

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'active');
    }
}
