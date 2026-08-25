<?php

namespace Tests\Unit\Models;

use App\Models\Product;
use App\Modules\Product\Enums\ProductType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductModelTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_persists_subscription_product_with_sku(): void
    {
        $product = Product::factory()->subscription()->create([
            'sku' => 'MODEL-TEST-001',
            'type' => ProductType::Subscription,
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'sku' => 'MODEL-TEST-001',
            'is_subscription' => true,
        ]);
    }
}
