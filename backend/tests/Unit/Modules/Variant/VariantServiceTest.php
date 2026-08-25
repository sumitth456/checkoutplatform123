<?php

namespace Tests\Unit\Modules\Variant;

use App\Models\Product;
use App\Models\Variant;
use App\Modules\Variant\Enums\VariantStatus;
use App\Modules\Variant\Repositories\VariantRepository;
use App\Modules\Variant\Services\VariantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VariantServiceTest extends TestCase
{
    use RefreshDatabase;

    private VariantService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new VariantService(new VariantRepository);
    }

    public function test_create_inherits_product_currency(): void
    {
        $product = Product::factory()->create(['currency' => 'EUR']);

        $variant = $this->service->create($product, [
            'sku' => 'EUR-001',
            'price' => 25.00,
        ]);

        $this->assertSame('EUR', $variant->currency);
        $this->assertSame($product->id, $variant->product_id);
    }

    public function test_create_default_variant_clears_other_defaults(): void
    {
        $product = Product::factory()->create();
        $first = Variant::factory()->default()->create(['product_id' => $product->id]);

        $second = $this->service->create($product, [
            'sku' => 'DEFAULT-002',
            'price' => 10,
            'is_default' => true,
        ]);

        $this->assertFalse($first->fresh()->is_default);
        $this->assertTrue($second->is_default);
    }

    public function test_restore_requires_soft_deleted_variant(): void
    {
        $variant = Variant::factory()->create();

        $this->expectException(ValidationException::class);

        $this->service->restore($variant->uuid);
    }

    public function test_list_for_product_only_returns_product_variants(): void
    {
        $product = Product::factory()->create();
        Variant::factory()->count(2)->create(['product_id' => $product->id]);
        Variant::factory()->create();

        $results = $this->service->listForProduct($product);

        $this->assertCount(2, $results);
    }
}
