<?php

namespace Tests\Feature\Api;

use App\Models\Funnel;
use App\Models\Product;
use App\Models\Variant;
use App\Modules\Order\Enums\DeviceType;
use App\Modules\Order\Events\OrderPlaced;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FunnelCheckoutApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_accepts_attribution_rich_funnel_order_payload(): void
    {
        Event::fake([OrderPlaced::class]);

        $funnel = Funnel::query()->create([
            'name' => 'Main Funnel',
            'slug' => 'main-offer',
            'status' => 'active',
            'currency' => 'USD',
        ]);

        $product = Product::factory()->active()->create(['sku' => 'FUNNEL-PRO-1', 'currency' => 'USD']);
        $variant = Variant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'FUNNEL-PRO-1-M',
            'price' => 49.99,
            'currency' => 'USD',
            'track_inventory' => false,
            'options' => ['size' => 'M'],
        ]);

        $response = $this->postJson('/api/v1/orders', [
            'product_id' => $product->uuid,
            'sku' => $product->sku,
            'variant' => ['size' => 'M'],
            'quantity' => 1,
            'customer' => [
                'email' => 'funnel-buyer@example.com',
                'first_name' => 'Funnel',
                'last_name' => 'Buyer',
                'phone' => '+15551234567',
            ],
            'shipping_address' => [
                'line1' => '123 Main St',
                'city' => 'Austin',
                'state' => 'TX',
                'postal_code' => '78701',
                'country' => 'US',
            ],
            'billing_address' => [
                'line1' => '123 Main St',
                'city' => 'Austin',
                'state' => 'TX',
                'postal_code' => '78701',
                'country' => 'US',
            ],
            'funnel_id' => $funnel->uuid,
            'campaign_id' => 'cmp-summer-2026',
            'currency' => 'USD',
            'country' => 'US',
            'utm' => [
                'source' => 'facebook',
                'medium' => 'cpc',
                'campaign' => 'subscribe_launch',
                'term' => 'checkout',
                'content' => 'hero-a',
            ],
            'landing_page' => [
                'url' => 'https://example.com/subscribe',
                'id' => 'lp-001',
                'referrer' => 'https://facebook.com',
                'data' => ['headline' => 'Limited Offer'],
            ],
        ], [
            'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.payment_status', 'pending')
            ->assertJsonPath('data.grand_total', '49.99')
            ->assertJsonPath('data.campaign_id', 'cmp-summer-2026')
            ->assertJsonPath('data.country_code', 'US')
            ->assertJsonPath('data.device_type', DeviceType::Mobile->value)
            ->assertJsonPath('data.utm_parameters.utm_source', 'facebook')
            ->assertJsonPath('data.landing_page_url', 'https://example.com/subscribe')
            ->assertJsonStructure([
                'data' => [
                    'uuid',
                    'order_number',
                    'funnel_id',
                    'customer',
                    'items',
                ],
            ]);

        $this->assertDatabaseHas('orders', [
            'customer_email' => 'funnel-buyer@example.com',
            'campaign_id' => 'cmp-summer-2026',
            'country_code' => 'US',
            'funnel_id' => $funnel->id,
        ]);

        $this->assertDatabaseHas('customers', [
            'email' => 'funnel-buyer@example.com',
            'orders_count' => 1,
        ]);

        Event::assertDispatched(OrderPlaced::class);
    }

    #[Test]
    public function it_exposes_legacy_post_api_orders_alias(): void
    {
        $variant = Variant::factory()->create([
            'track_inventory' => false,
            'price' => 10.00,
            'currency' => 'USD',
        ]);

        $this->postJson('/api/orders', [
            'customer' => ['email' => 'alias@example.com'],
            'items' => [
                ['variant_uuid' => $variant->uuid, 'quantity' => 1],
            ],
        ])->assertCreated();
    }
}
