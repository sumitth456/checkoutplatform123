<?php

namespace Tests\Unit\Campaign;

use App\Models\Campaign;
use App\Models\Product;
use App\Models\SubscriptionPlan;
use App\Modules\Campaign\Services\CampaignProductBulkService;
use App\Modules\Campaign\Services\SubscriptionPlanCampaignBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignSubscriptionPlanBillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_add_inherits_cycles_from_campaign_subscription_plan(): void
    {
        $plan = SubscriptionPlan::factory()->checkoutChampTestProductPlan()->create();
        $campaign = Campaign::factory()->create(['subscription_plan_id' => $plan->id]);
        $product = Product::factory()->create(['base_price' => 99.99]);

        app(CampaignProductBulkService::class)->bulkAdd($campaign, [$product->id]);

        $campaignProduct = $campaign->campaignProducts()->where('product_id', $product->id)->first();
        $this->assertNotNull($campaignProduct);
        $this->assertSame(3, $campaignProduct->billingCycles()->count());
        $this->assertSame('39.99', $campaignProduct->billingCycles()->where('cycle_number', 1)->value('price'));
        $this->assertSame($product->id, (int) $campaignProduct->billingCycles()->where('cycle_number', 1)->value('product_id'));
        $this->assertTrue($campaignProduct->billingCycles()->where('cycle_number', 1)->value('subscription_enabled'));

        $product->refresh();
        $this->assertTrue($product->is_subscription);
        $this->assertSame($plan->id, $product->subscription_plan_id);
    }

    public function test_apply_plan_preserves_bill_product_sku_overrides(): void
    {
        $plan = SubscriptionPlan::factory()->checkoutChampTestProductPlan()->create();
        $campaign = Campaign::factory()->create(['subscription_plan_id' => $plan->id]);
        $productA = Product::factory()->create();
        $productB = Product::factory()->create();

        app(CampaignProductBulkService::class)->bulkAdd($campaign, [$productA->id]);

        $campaignProduct = $campaign->campaignProducts()->first();
        $planBilling = app(SubscriptionPlanCampaignBillingService::class);

        $planBilling->syncSkuOverrides($campaignProduct, [
            ['cycle_number' => 1, 'product_id' => $productB->id, 'variant_id' => null],
        ]);

        $plan->steps()->where('step_order', 1)->update(['amount' => 19.99]);
        $planBilling->applyPlanToCampaign($campaign->fresh(['subscriptionPlan.steps', 'campaignProducts.billingCycles']));

        $cycleOne = $campaignProduct->fresh()->billingCycles()->where('cycle_number', 1)->first();
        $this->assertSame('19.99', $cycleOne->price);
        $this->assertSame($productB->id, $cycleOne->product_id);
    }
}
