<?php

namespace App\Demo;

use App\Models\Campaign;
use App\Models\CampaignProduct;
use App\Models\DunningPolicy;
use App\Models\LandingPage;
use App\Models\PaymentGatewayConfig;
use App\Models\Product;
use App\Models\ProductBillingCycle;
use App\Models\RetryPolicy;
use App\Models\SubscriptionPlan;
use App\Models\Variant;
use App\Modules\Campaign\Enums\CampaignProductInventoryRule;
use App\Modules\Campaign\Enums\CampaignStatus;
use App\Modules\LandingPage\Enums\LandingPageStatus;
use App\Modules\Product\BillingCycles\ProductBillingCycleService;
use App\Modules\Product\Enums\ProductStatus;
use App\Modules\Product\Enums\ProductType;
use App\Modules\Subscription\Enums\BillingCycleType;
use App\Modules\Subscription\Enums\BillingStepType;
use App\Modules\Variant\Enums\InventoryPolicy;
use App\Modules\Variant\Enums\VariantStatus;
use Illuminate\Support\Str;

final class CheckoutChampScenarioBuilder
{
    public Campaign $campaign;

    public Product $baseProduct;

    public Product $rebillProduct;

    public Variant $mediumVariant;

    public SubscriptionPlan $subscriptionPlan;

    public LandingPage $landingPage;

    public PaymentGatewayConfig $stripeGateway;

    public function buildIfMissing(): self
    {
        $existing = Campaign::query()->where('public_id', 'TEST-CAMP-001')->first();

        if ($existing !== null) {
            $this->campaign = $existing;
            $this->baseProduct = Product::query()->where('sku', 'TEST-001')->firstOrFail();
            $this->rebillProduct = Product::query()->where('sku', 'TEST-001-RB')->firstOrFail();
            $this->mediumVariant = Variant::query()
                ->where('product_id', $this->baseProduct->id)
                ->where('name', 'Medium')
                ->firstOrFail();
            $this->subscriptionPlan = $this->baseProduct->subscriptionPlan
                ?? SubscriptionPlan::query()->where('name', 'Test Subscription Plan')->firstOrFail();
            $this->landingPage = LandingPage::query()->where('slug', 'test-product')->firstOrFail();
            $this->stripeGateway = PaymentGatewayConfig::query()
                ->where('provider', 'stripe')
                ->where('is_active', true)
                ->orderByDesc('is_default')
                ->firstOrFail();

            return $this;
        }

        return $this->build();
    }

    public function build(): self
    {
        $this->stripeGateway = PaymentGatewayConfig::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Stripe Sandbox',
            'slug' => 'stripe-sandbox-'.Str::lower(Str::random(4)),
            'provider' => 'stripe',
            'credentials' => ['secret_key' => 'sk_test'],
            'is_active' => true,
            'is_default' => true,
            'supports_subscriptions' => true,
            'failover_enabled' => true,
            'sort_order' => 1,
            'weight' => 100,
            'test_mode' => true,
        ]);

        PaymentGatewayConfig::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'PayPal Sandbox',
            'slug' => 'paypal-sandbox-'.Str::lower(Str::random(4)),
            'provider' => 'paypal',
            'credentials' => ['client_id' => 'test', 'client_secret' => 'test'],
            'is_active' => true,
            'is_default' => false,
            'supports_subscriptions' => true,
            'failover_enabled' => true,
            'sort_order' => 2,
            'weight' => 100,
            'test_mode' => true,
        ]);

        $this->campaign = Campaign::query()->create([
            'uuid' => (string) Str::uuid(),
            'public_id' => 'TEST-CAMP-001',
            'name' => 'Test Campaign',
            'description' => 'Complete recurring subscription testing campaign.',
            'status' => CampaignStatus::Active,
            'currency' => 'USD',
            'metadata' => ['external_id' => 'TEST-CAMP-001'],
        ]);

        $this->baseProduct = $this->createSubscriptionProduct(
            name: 'Test-1 Product',
            sku: 'TEST-001',
            slug: 'test-1-product',
            basePrice: 9.99,
        );

        $this->rebillProduct = $this->createSubscriptionProduct(
            name: 'Test-1 Product Rebill',
            sku: 'TEST-001-RB',
            slug: 'test-1-product-rebill',
            basePrice: 39.99,
        );

        foreach (['Small', 'Medium', 'Large', 'XL'] as $size) {
            $variant = $this->createVariant(
                productId: $this->baseProduct->id,
                name: $size,
                sku: 'TEST-001-'.strtoupper($size),
                isDefault: $size === 'Medium',
            );

            if ($size === 'Medium') {
                $this->mediumVariant = $variant;
            }
        }

        $this->subscriptionPlan = $this->createTestSubscriptionPlan();

        $this->baseProduct->update(['subscription_plan_id' => $this->subscriptionPlan->id]);

        $cycleService = app(ProductBillingCycleService::class);
        $cycleService->addCycle($this->baseProduct, [
            'cycle_number' => 1,
            'bill_product_id' => $this->baseProduct->id,
            'variant_id' => $this->mediumVariant->id,
            'price' => 9.99,
            'shipping_price' => 0,
            'bill_delay_days' => 0,
            'delay_fulfillment' => true,
            'shippable' => true,
            'repeat_forever' => false,
        ]);
        $cycleService->addCycle($this->baseProduct, [
            'cycle_number' => 2,
            'bill_product_id' => $this->rebillProduct->id,
            'price' => 39.99,
            'shipping_price' => 0,
            'bill_delay_days' => 7,
            'delay_fulfillment' => false,
            'shippable' => true,
            'repeat_forever' => false,
        ]);
        $cycleService->addCycle($this->baseProduct, [
            'cycle_number' => 3,
            'bill_product_id' => $this->rebillProduct->id,
            'price' => 39.99,
            'shipping_price' => 0,
            'bill_delay_days' => 30,
            'delay_fulfillment' => false,
            'shippable' => true,
            'repeat_forever' => true,
        ]);

        CampaignProduct::query()->create([
            'campaign_id' => $this->campaign->id,
            'product_id' => $this->baseProduct->id,
            'variant_id' => $this->mediumVariant->id,
            'sort_order' => 1,
            'is_active' => true,
            'inventory_rule' => CampaignProductInventoryRule::Inherit,
        ]);

        $this->landingPage = LandingPage::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Test Product Landing Page',
            'slug' => 'test-product',
            'status' => LandingPageStatus::Published,
            'campaign_id' => $this->campaign->id,
            'seo_title' => 'Test Product Landing Page',
            'content_html' => '<section><h1>Test Product</h1><p>CheckoutChamp demo offer.</p><form id="checkout-form"></form></section>',
            'content_css' => 'body { font-family: system-ui, sans-serif; margin: 2rem; }',
            'content_js' => '',
            'published_at' => now(),
        ]);

        ProductBillingCycle::query()
            ->where('product_id', $this->baseProduct->id)
            ->where('cycle_number', 1)
            ->first()
            ?->update(['delay_fulfillment' => true, 'bill_delay_days' => 0]);

        return $this;
    }

    private function createSubscriptionProduct(string $name, string $sku, string $slug, float $basePrice): Product
    {
        return Product::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'slug' => $slug,
            'description' => 'CheckoutChamp demo subscription product.',
            'short_description' => 'Demo subscription SKU '.$sku,
            'type' => ProductType::Subscription,
            'status' => ProductStatus::Active,
            'sku' => $sku,
            'currency' => 'USD',
            'base_price' => $basePrice,
            'is_taxable' => true,
            'requires_shipping' => true,
            'is_subscription' => true,
            'billing_interval' => 'month',
            'billing_interval_count' => 1,
            'trial_days' => 7,
            'images' => [],
            'metadata' => [],
            'published_at' => now(),
        ]);
    }

    private function createVariant(int $productId, string $name, string $sku, bool $isDefault): Variant
    {
        return Variant::query()->create([
            'uuid' => (string) Str::uuid(),
            'product_id' => $productId,
            'name' => $name,
            'sku' => $sku,
            'currency' => 'USD',
            'price' => 9.99,
            'inventory_quantity' => 100,
            'inventory_policy' => InventoryPolicy::Deny,
            'track_inventory' => true,
            'is_default' => $isDefault,
            'status' => VariantStatus::Active,
            'options' => [],
            'images' => [],
            'metadata' => [],
        ]);
    }

    private function createTestSubscriptionPlan(): SubscriptionPlan
    {
        $retry = RetryPolicy::query()->firstOrCreate(
            ['name' => 'Demo Standard Retry'],
            [
                'uuid' => (string) Str::uuid(),
                'max_attempts' => 3,
                'retry_offsets' => [1, 3, 7],
            ],
        );

        $dunning = DunningPolicy::query()->firstOrCreate(
            ['name' => 'Demo Dunning'],
            [
                'uuid' => (string) Str::uuid(),
                'is_active' => true,
                'stages' => [
                    ['day_offset' => 1, 'channel' => 'email', 'template' => 'payment_failed_day_1'],
                ],
            ],
        );

        $plan = SubscriptionPlan::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Test Subscription Plan',
            'slug' => 'test-subscription-plan-'.Str::lower(Str::random(6)),
            'currency' => 'USD',
            'retry_policy_id' => $retry->id,
            'dunning_policy_id' => $dunning->id,
            'is_active' => true,
            'metadata' => [],
        ]);

        $plan->steps()->createMany([
            [
                'step_order' => 1,
                'billing_type' => BillingStepType::OneTime->value,
                'cycle_type' => BillingCycleType::Day7Rebill->value,
                'days_from_start' => 7,
                'amount' => 39.99,
                'currency' => 'USD',
            ],
            [
                'step_order' => 2,
                'billing_type' => BillingStepType::OneTime->value,
                'cycle_type' => BillingCycleType::Day30Rebill->value,
                'days_from_start' => 30,
                'amount' => 39.99,
                'currency' => 'USD',
            ],
            [
                'step_order' => 3,
                'billing_type' => BillingStepType::Recurring->value,
                'cycle_type' => BillingCycleType::Custom->value,
                'interval_days' => 30,
                'amount' => 39.99,
                'currency' => 'USD',
            ],
        ]);

        return $plan;
    }

    public function publicLandingUrl(): string
    {
        $prefix = trim((string) config('landing_pages.public_path_prefix', 'offer'), '/');

        return url('/'.$prefix.'/'.$this->landingPage->slug);
    }
}
