<?php

namespace Tests\Unit\Models;

use App\Models\Product;
use App\Models\Variant;
use App\Modules\Variant\Enums\InventoryPolicy;
use App\Modules\Variant\Enums\VariantStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VariantModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigns_uuid_on_create(): void
    {
        $variant = Variant::factory()->create();

        $this->assertNotEmpty($variant->uuid);
    }

    public function test_casts_status_and_inventory_policy_to_enums(): void
    {
        $variant = Variant::factory()->create([
            'status' => VariantStatus::Inactive,
            'inventory_policy' => InventoryPolicy::Continue,
        ]);

        $this->assertSame(VariantStatus::Inactive, $variant->status);
        $this->assertSame(InventoryPolicy::Continue, $variant->inventory_policy);
    }

    public function test_belongs_to_product(): void
    {
        $product = Product::factory()->create();
        $variant = Variant::factory()->create(['product_id' => $product->id]);

        $this->assertTrue($variant->product->is($product));
    }
}
