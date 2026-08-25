<?php

namespace Tests\Unit\Campaign;

use App\Models\Campaign;
use App\Models\Product;
use App\Models\ProductBillingCycle;
use App\Models\Variant;
use App\Modules\Campaign\Services\CampaignProductBulkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignProductBillingCycleInheritanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_add_inherits_product_billing_cycles(): void
    {
        $campaign = Campaign::factory()->create();
        $product = Product::factory()->create();
        Variant::factory()->create(['product_id' => $product->id, 'is_default' => true]);

        ProductBillingCycle::query()->create([
            'product_id' => $product->id,
            'cycle_number' => 1,
            'bill_product_id' => $product->id,
            'price' => 19.99,
            'bill_delay_days' => 0,
            'delay_fulfillment' => false,
            'shippable' => true,
            'subscription_enabled' => true,
            'repeat_forever' => false,
            'inventory_rule' => 'inherit',
        ]);

        ProductBillingCycle::query()->create([
            'product_id' => $product->id,
            'cycle_number' => 2,
            'bill_product_id' => $product->id,
            'price' => 29.99,
            'bill_delay_days' => 30,
            'delay_fulfillment' => false,
            'shippable' => true,
            'subscription_enabled' => false,
            'repeat_forever' => false,
            'inventory_rule' => 'inherit',
        ]);

        app(CampaignProductBulkService::class)->bulkAdd($campaign, [$product->id]);

        $campaignProduct = $campaign->campaignProducts()->where('product_id', $product->id)->first();
        $this->assertNotNull($campaignProduct);
        $this->assertSame(2, $campaignProduct->billingCycles()->count());
        $this->assertSame('19.99', $campaignProduct->billingCycles()->where('cycle_number', 1)->value('price'));
    }
}
