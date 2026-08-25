<?php

namespace Tests\Unit\Campaign;

use App\Models\Campaign;
use App\Models\Product;
use App\Modules\Campaign\Services\CampaignProductBulkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CampaignProductBulkServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_bulk_adds_products_to_campaign(): void
    {
        $campaign = Campaign::factory()->create();
        $product = Product::factory()->create();

        app(CampaignProductBulkService::class)->bulkAdd($campaign, [$product->id]);

        $this->assertDatabaseHas('campaign_product', [
            'campaign_id' => $campaign->id,
            'product_id' => $product->id,
        ]);
    }
}
