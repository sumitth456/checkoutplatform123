<?php

namespace Tests\Feature\Api;

use App\Models\Campaign;
use App\Models\CampaignProduct;
use App\Models\Product;
use App\Models\ProductBillingCycle;
use App\Models\User;
use App\Models\Variant;
use App\Modules\Product\BillingCycles\ProductBillingCycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BillingCycleApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_billing_cycle_crud_and_sync(): void
    {
        Sanctum::actingAs(User::factory()->create()->assignRole(
            \Spatie\Permission\Models\Role::findOrCreate('Admin'),
        ));

        $product = Product::factory()->create();
        $rebilled = Product::factory()->create(['sku' => 'REBILL-1']);
        Variant::factory()->create(['product_id' => $rebilled->id, 'sku' => 'REBILL-V1']);

        $create = $this->postJson("/api/v1/products/{$product->uuid}/billing-cycles", [
            'cycle_number' => 1,
            'bill_product_id' => $rebilled->id,
            'price' => 39.99,
            'subscription_enabled' => true,
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.cycle_number', 1)
            ->assertJsonPath('data.price', '39.99');

        $cycleId = $create->json('data.id');

        $this->patchJson("/api/v1/products/{$product->uuid}/billing-cycles/{$cycleId}", [
            'price' => 49.99,
        ])->assertOk()->assertJsonPath('data.price', '49.99');

        $sync = $this->postJson("/api/v1/products/{$product->uuid}/billing-cycles/sync", [
            'cycles' => [
                ['cycle_number' => 1, 'bill_product_id' => $rebilled->id, 'price' => 10],
                ['cycle_number' => 2, 'bill_product_id' => $rebilled->id, 'price' => 20, 'repeat_forever' => true],
            ],
        ]);

        $sync->assertOk()->assertJsonPath('synced', 2);

        $this->getJson("/api/v1/products/{$product->uuid}/billing-cycles?inline=1")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_product_billing_cycle_json_export_and_import(): void
    {
        Sanctum::actingAs(User::factory()->create()->assignRole(
            \Spatie\Permission\Models\Role::findOrCreate('Admin'),
        ));

        $product = Product::factory()->create(['sku' => 'BASE-1']);
        app(ProductBillingCycleService::class)->ensureDefaultTemplate($product);

        $export = $this->getJson("/api/v1/products/{$product->uuid}/billing-cycles/export?format=json&inline=1");
        $export->assertOk()->assertJsonStructure(['cycles']);

        $product->billingCycles()->delete();

        $import = $this->postJson("/api/v1/products/{$product->uuid}/billing-cycles/import", [
            'cycles' => [
                [
                    'cycle_number' => 1,
                    'product_id' => $product->id,
                    'price' => 11.11,
                ],
            ],
        ]);

        $import->assertOk()->assertJsonPath('imported', 1);
        $this->assertSame('11.11', $product->fresh()->billingCycles()->value('price'));
    }

    public function test_campaign_product_billing_cycle_endpoints(): void
    {
        Sanctum::actingAs(User::factory()->create()->assignRole(
            \Spatie\Permission\Models\Role::findOrCreate('Admin'),
        ));

        $campaign = Campaign::factory()->create();
        $product = Product::factory()->create();
        $line = CampaignProduct::query()->create([
            'campaign_id' => $campaign->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'priority' => 100,
            'sort_order' => 1,
            'inventory_rule' => 'inherit',
            'is_active' => true,
        ]);

        ProductBillingCycle::query()->create([
            'product_id' => $product->id,
            'cycle_number' => 1,
            'bill_product_id' => $product->id,
            'price' => 5,
            'bill_delay_days' => 0,
            'delay_fulfillment' => false,
            'shippable' => true,
            'subscription_enabled' => true,
            'repeat_forever' => false,
            'inventory_rule' => 'inherit',
        ]);

        $inherit = $this->postJson(
            "/api/v1/campaigns/{$campaign->uuid}/product-lines/{$line->id}/billing-cycles/inherit-from-product",
        );

        $inherit->assertOk()->assertJsonPath('imported', 1);

        $this->getJson("/api/v1/campaigns/{$campaign->uuid}/billing-cycles/export?format=json&inline=1")
            ->assertOk()
            ->assertJsonStructure(['product_lines']);
    }
}
