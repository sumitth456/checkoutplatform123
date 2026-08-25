<?php

namespace App\Demo;

use App\Models\Campaign;
use App\Models\CampaignProduct;
use App\Models\Product;
use App\Models\ProductBillingCycle;
use App\Models\Variant;
use App\Modules\Campaign\Enums\CampaignProductInventoryRule;
use App\Modules\Campaign\Services\CampaignProductBillingCycleService;
use App\Modules\Product\BillingCycles\ProductBillingCycleService;
use App\Modules\Product\Enums\ProductStatus;
use App\Modules\Product\Enums\ProductType;
use App\Modules\Variant\Enums\InventoryPolicy;
use App\Modules\Variant\Enums\VariantStatus;
use Illuminate\Support\Str;

final class MensWomensTshirtCatalogBuilder
{
    private const COLORS = ['White', 'Black'];

    private const SIZES = ['S', 'M', 'L', 'XL', 'XXL', '3XL', '4XL'];

    public function __construct(
        private readonly ProductBillingCycleService $billingCycles,
        private readonly CampaignProductBillingCycleService $campaignBillingCycles,
    ) {}

    /**
     * @return array{
     *     campaign: Campaign,
     *     mens_product: Product,
     *     womens_product: Product,
     *     mens_campaign_line: CampaignProduct,
     *     womens_campaign_line: CampaignProduct
     * }
     */
    public function seed(string $campaignPublicId = 'TEST-CAMP-001'): array
    {
        $campaign = Campaign::query()->where('public_id', $campaignPublicId)->firstOrFail();
        $rebillProduct = Product::query()->where('sku', 'TEST-001-RB')->first();

        $mensProduct = $this->upsertProduct(
            sku: 'MENS-TSHIRT',
            name: "Men's T-Shirt",
            slug: 'mens-t-shirt',
            audience: 'mens',
        );

        $womensProduct = $this->upsertProduct(
            sku: 'WOMENS-TSHIRT',
            name: "Women's T-Shirt",
            slug: 'womens-t-shirt',
            audience: 'womens',
        );

        $mensDefaultVariant = $this->syncVariantMatrix($mensProduct, 'MENS-TSHIRT');
        $womensDefaultVariant = $this->syncVariantMatrix($womensProduct, 'WOMENS-TSHIRT');

        $this->syncProductBillingCycles($mensProduct, $mensDefaultVariant, $rebillProduct);
        $this->syncProductBillingCycles($womensProduct, $womensDefaultVariant, $rebillProduct);

        CampaignProduct::query()
            ->where('campaign_id', $campaign->id)
            ->whereHas('product', fn ($query) => $query->where('sku', 'TEST-001'))
            ->update(['is_active' => false]);

        $mensLine = $this->attachToCampaign($campaign, $mensProduct, $mensDefaultVariant, sortOrder: 1);
        $womensLine = $this->attachToCampaign($campaign, $womensProduct, $womensDefaultVariant, sortOrder: 2);

        return [
            'campaign' => $campaign->fresh(),
            'mens_product' => $mensProduct->fresh('variants'),
            'womens_product' => $womensProduct->fresh('variants'),
            'mens_campaign_line' => $mensLine,
            'womens_campaign_line' => $womensLine,
        ];
    }

    private function upsertProduct(string $sku, string $name, string $slug, string $audience): Product
    {
        $product = Product::query()->firstOrNew(['sku' => $sku]);

        $product->fill([
            'uuid' => $product->uuid ?: (string) Str::uuid(),
            'name' => $name,
            'slug' => $slug,
            'description' => "{$name} with full color and size matrix.",
            'short_description' => $sku,
            'type' => ProductType::Subscription,
            'status' => ProductStatus::Active,
            'currency' => 'USD',
            'base_price' => 9.99,
            'is_taxable' => true,
            'requires_shipping' => true,
            'is_subscription' => true,
            'billing_interval' => 'month',
            'billing_interval_count' => 1,
            'trial_days' => 0,
            'images' => [],
            'metadata' => [
                'audience' => $audience,
                'legacy_gender' => $audience === 'mens' ? 'm' : 'w',
            ],
            'published_at' => $product->published_at ?? now(),
        ]);

        $existingPlanId = Product::query()->where('sku', 'TEST-001')->value('subscription_plan_id');

        if ($existingPlanId !== null) {
            $product->subscription_plan_id = $existingPlanId;
        }

        $product->save();

        return $product;
    }

    private function syncVariantMatrix(Product $product, string $skuPrefix): Variant
    {
        $defaultVariant = null;

        foreach (self::COLORS as $color) {
            foreach (self::SIZES as $size) {
                $variantSku = sprintf('%s-%s-%s', $skuPrefix, strtoupper($color), strtoupper($size));

                $variant = Variant::query()->firstOrNew([
                    'product_id' => $product->id,
                    'sku' => $variantSku,
                ]);

                $isDefault = $color === 'White' && $size === 'M';

                $variant->fill([
                    'uuid' => $variant->uuid ?: (string) Str::uuid(),
                    'name' => "{$color} / {$size}",
                    'currency' => 'USD',
                    'price' => 9.99,
                    'inventory_quantity' => 100,
                    'inventory_policy' => InventoryPolicy::Deny,
                    'track_inventory' => true,
                    'is_default' => $isDefault,
                    'status' => VariantStatus::Active,
                    'options' => [
                        'color' => $color,
                        'size' => $size,
                    ],
                    'images' => [],
                    'metadata' => [],
                ]);

                $variant->save();

                if ($isDefault) {
                    $defaultVariant = $variant;
                }
            }
        }

        Variant::query()
            ->where('product_id', $product->id)
            ->whereNotIn('sku', $this->expectedSkus($skuPrefix))
            ->update(['status' => VariantStatus::Inactive]);

        return $defaultVariant ?? Variant::query()->where('product_id', $product->id)->firstOrFail();
    }

    /**
     * @return list<string>
     */
    private function expectedSkus(string $skuPrefix): array
    {
        $skus = [];

        foreach (self::COLORS as $color) {
            foreach (self::SIZES as $size) {
                $skus[] = sprintf('%s-%s-%s', $skuPrefix, strtoupper($color), strtoupper($size));
            }
        }

        return $skus;
    }

    private function syncProductBillingCycles(Product $product, Variant $defaultVariant, ?Product $rebillProduct): void
    {
        ProductBillingCycle::query()->where('product_id', $product->id)->delete();

        $this->billingCycles->addCycle($product, [
            'cycle_number' => 1,
            'bill_product_id' => $product->id,
            'variant_id' => $defaultVariant->id,
            'price' => 9.99,
            'shipping_price' => 0,
            'bill_delay_days' => 0,
            'delay_fulfillment' => true,
            'shippable' => true,
            'repeat_forever' => false,
        ]);

        if ($rebillProduct === null) {
            return;
        }

        $this->billingCycles->addCycle($product, [
            'cycle_number' => 2,
            'bill_product_id' => $rebillProduct->id,
            'price' => 39.99,
            'shipping_price' => 0,
            'bill_delay_days' => 7,
            'delay_fulfillment' => false,
            'shippable' => true,
            'repeat_forever' => false,
        ]);

        $this->billingCycles->addCycle($product, [
            'cycle_number' => 3,
            'bill_product_id' => $rebillProduct->id,
            'price' => 39.99,
            'shipping_price' => 0,
            'bill_delay_days' => 30,
            'delay_fulfillment' => false,
            'shippable' => true,
            'repeat_forever' => true,
        ]);
    }

    private function attachToCampaign(
        Campaign $campaign,
        Product $product,
        Variant $defaultVariant,
        int $sortOrder,
    ): CampaignProduct {
        $line = CampaignProduct::query()->updateOrCreate(
            [
                'campaign_id' => $campaign->id,
                'product_id' => $product->id,
            ],
            [
                'variant_id' => $defaultVariant->id,
                'quantity' => 1,
                'priority' => 100,
                'sort_order' => $sortOrder,
                'is_active' => true,
                'inventory_rule' => CampaignProductInventoryRule::Inherit,
            ],
        );

        $this->campaignBillingCycles->inheritFromProductDefaults($line->fresh());

        return $line->fresh(['product', 'billingCycles']);
    }
}
