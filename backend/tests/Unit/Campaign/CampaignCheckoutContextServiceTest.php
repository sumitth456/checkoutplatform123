<?php

namespace Tests\Unit\Campaign;

use App\Models\Campaign;
use App\Models\Product;
use App\Models\Variant;
use App\Modules\Campaign\Services\CampaignCheckoutContextService;
use App\Modules\Campaign\Services\CampaignVariantMatrixService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CampaignCheckoutContextServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_builds_dynamic_product_matrix_from_variant_options(): void
    {
        $product = Product::factory()->create(['name' => 'Patriotic Shirt']);
        Variant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'SHIRT-WHITE-S',
            'price' => 39.99,
            'options' => ['color' => 'White', 'size' => 'S'],
            'inventory_quantity' => 5,
            'track_inventory' => true,
        ]);
        Variant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'SHIRT-BLACK-M',
            'price' => 44.99,
            'options' => ['color' => 'Black', 'size' => 'M'],
            'inventory_quantity' => 0,
            'track_inventory' => true,
        ]);

        $campaign = Campaign::factory()->create(['currency' => 'USD']);
        $campaign->campaignProducts()->create([
            'product_id' => $product->id,
            'campaign_price' => 39.99,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $payload = app(CampaignCheckoutContextService::class)->build($campaign->fresh());

        $this->assertSame($campaign->uuid, $payload['campaign']['uuid']);
        $this->assertCount(1, $payload['products']);
        $this->assertContains('White', $payload['products'][0]['colors']);
        $this->assertContains('Black', $payload['products'][0]['colors']);
        $this->assertContains('S', $payload['products'][0]['sizes']);
        $this->assertContains('M', $payload['products'][0]['sizes']);
    }

    #[Test]
    public function variant_matrix_resolves_color_and_size_to_uuid(): void
    {
        $product = Product::factory()->create();
        $white = Variant::factory()->create([
            'product_id' => $product->id,
            'options' => ['color' => 'White', 'size' => 'XL'],
        ]);

        $line = new \App\Models\CampaignProduct([
            'product_id' => $product->id,
            'campaign_price' => null,
        ]);
        $line->setRelation('product', $product->load('variants'));

        $uuid = app(CampaignVariantMatrixService::class)->findVariantUuid($line, 'White', 'XL');

        $this->assertSame($white->uuid, $uuid);
    }
}
