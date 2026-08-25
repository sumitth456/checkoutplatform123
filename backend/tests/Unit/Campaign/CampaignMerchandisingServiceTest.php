<?php

namespace Tests\Unit\Campaign;

use App\Models\Campaign;
use App\Models\Product;
use App\Modules\Campaign\Enums\CampaignDiscountType;
use App\Modules\Campaign\Enums\CampaignOfferPosition;
use App\Modules\Campaign\Enums\CampaignOfferTrigger;
use App\Modules\Campaign\Enums\CampaignOrderBumpDisplayRule;
use App\Modules\Campaign\Services\CampaignMerchandisingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignMerchandisingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_merchandising_and_build_checkout_payload(): void
    {
        $campaign = Campaign::factory()->create();
        $upsellProduct = Product::factory()->create(['sku' => 'UP-1']);
        $bumpProduct = Product::factory()->create(['sku' => 'BUMP-1']);

        $service = app(CampaignMerchandisingService::class);

        $service->createUpsell($campaign, [
            'product_id' => $upsellProduct->id,
            'priority' => 200,
            'trigger' => CampaignOfferTrigger::Always->value,
            'position' => CampaignOfferPosition::PostCheckout->value,
            'discount_type' => CampaignDiscountType::Percent->value,
            'discount_value' => 10,
            'is_active' => true,
        ]);

        $service->createDownsell($campaign, [
            'product_id' => $upsellProduct->id,
            'priority' => 150,
            'trigger' => CampaignOfferTrigger::DeclinedUpsell->value,
            'discount_type' => CampaignDiscountType::None->value,
            'is_active' => true,
        ]);

        $service->createOrderBump($campaign, [
            'product_id' => $bumpProduct->id,
            'bump_price' => 19.99,
            'position' => CampaignOfferPosition::PreCheckout->value,
            'display_rule' => CampaignOrderBumpDisplayRule::Always->value,
            'is_active' => true,
        ]);

        $payload = $service->checkoutPayload($campaign);

        $this->assertCount(1, $payload['upsells']);
        $this->assertSame('UP-1', $payload['upsells'][0]['product']['sku']);
        $this->assertSame('percent', $payload['upsells'][0]['discount_type']);
        $this->assertCount(1, $payload['downsells']);
        $this->assertCount(1, $payload['order_bumps']);
        $this->assertSame('19.99', (string) $payload['order_bumps'][0]['bump_price']);
    }

    public function test_copy_merchandising_on_campaign_duplicate(): void
    {
        $source = Campaign::factory()->create();
        $target = Campaign::factory()->create();
        $product = Product::factory()->create();

        $service = app(CampaignMerchandisingService::class);
        $service->createUpsell($source, [
            'product_id' => $product->id,
            'trigger' => CampaignOfferTrigger::Always->value,
            'position' => CampaignOfferPosition::OneClickUpsell->value,
            'discount_type' => CampaignDiscountType::None->value,
            'is_active' => true,
        ]);

        $counts = $service->copyMerchandising($source->fresh(['upsells', 'downsells', 'orderBumps']), $target);

        $this->assertSame(1, $counts['upsells']);
        $this->assertSame(1, $target->upsells()->count());
    }
}
