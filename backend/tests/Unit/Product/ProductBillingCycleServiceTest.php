<?php

namespace Tests\Unit\Product;

use App\Models\Product;
use App\Models\ProductBillingCycle;
use App\Models\Variant;
use App\Modules\Product\BillingCycles\ProductBillingCycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductBillingCycleServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_ensure_default_template_creates_three_cycles(): void
    {
        $product = Product::factory()->create(['base_price' => 49.99, 'requires_shipping' => true]);
        Variant::factory()->create(['product_id' => $product->id, 'is_default' => true]);

        $service = app(ProductBillingCycleService::class);
        $cycles = $service->ensureDefaultTemplate($product);

        $this->assertCount(3, $cycles);
        $this->assertSame(1, $cycles[0]->cycle_number);
        $this->assertTrue($cycles[2]->repeat_forever);
        $this->assertSame('49.99', $cycles[0]->price);
    }

    public function test_export_for_campaign_inheritance_maps_bill_product(): void
    {
        $product = Product::factory()->create();
        $rebilled = Product::factory()->create();

        ProductBillingCycle::query()->create([
            'product_id' => $product->id,
            'cycle_number' => 1,
            'bill_product_id' => $rebilled->id,
            'price' => 10,
            'bill_delay_days' => 0,
            'delay_fulfillment' => false,
            'shippable' => true,
            'subscription_enabled' => true,
            'repeat_forever' => false,
            'inventory_rule' => 'inherit',
        ]);

        $rows = app(ProductBillingCycleService::class)->exportForCampaignInheritance($product);

        $this->assertCount(1, $rows);
        $this->assertSame($rebilled->id, $rows[0]['product_id']);
    }
}
