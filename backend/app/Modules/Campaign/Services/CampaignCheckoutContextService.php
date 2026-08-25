<?php

namespace App\Modules\Campaign\Services;

use App\Models\Campaign;
use App\Models\CampaignProduct;
use App\Models\LandingPage;
use App\Models\Variant;
use App\Modules\Campaign\Enums\CampaignStatus;
use App\Modules\LandingPage\Services\LandingPageFormService;
use App\Modules\LandingPage\Services\LandingPageVariableReplacer;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class CampaignCheckoutContextService
{
    public function __construct(
        private readonly CampaignMerchandisingService $merchandising,
        private readonly CampaignVariantMatrixService $variantMatrix,
        private readonly CampaignCheckoutPricingService $pricing,
        private readonly LandingPageFormService $forms,
        private readonly LandingPageVariableReplacer $variables,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Campaign $campaign): array
    {
        $campaign->loadMissing([
            'landingPage.forms',
            'subscriptionPlan.steps',
            'subscriptionPlan.paymentGateway',
        ]);

        $lines = $campaign->campaignProducts()
            ->where('is_active', true)
            ->with(['product', 'billingCycles.product', 'billingCycles.variant'])
            ->orderBy('sort_order')
            ->get();

        $products = $lines
            ->map(fn (CampaignProduct $line): ?array => $this->serializeProductLine($campaign, $line))
            ->filter()
            ->values()
            ->all();

        $landingPage = $campaign->landingPage;
        $checkoutForm = $landingPage ? $this->forms->activeCheckoutForm($landingPage) : null;

        $payload = [
            'campaign' => [
                'uuid' => $campaign->uuid,
                'public_id' => $campaign->public_id,
                'name' => $campaign->name,
                'description' => $campaign->description,
                'currency' => strtoupper((string) ($campaign->currency ?: 'USD')),
                'store_name' => $campaign->store_name,
                'status' => $campaign->status->value,
            ],
            'landing_page' => $landingPage ? [
                'uuid' => $landingPage->uuid,
                'slug' => $landingPage->slug,
                'name' => $landingPage->name,
                'public_url' => $landingPage->publicUrl(),
            ] : null,
            'currency' => strtoupper((string) ($campaign->currency ?: 'USD')),
            'subscription_plan' => $this->serializeSubscriptionPlan($campaign),
            'products' => $products,
            'merchandising' => $this->merchandising->checkoutPayload($campaign),
            'shipping' => (array) ($campaign->metadata['shipping'] ?? []),
            'taxes' => (array) ($campaign->metadata['taxes'] ?? []),
            'checkout_form' => $checkoutForm ? [
                'submit_label' => $checkoutForm->settings['submit_label'] ?? 'Complete checkout',
                'field_schema' => $checkoutForm->field_schema ?? [],
            ] : null,
            'urls' => [
                'checkout' => url('/api/v1/checkout'),
                'payment_intent' => url('/api/v1/checkout/payment-intent'),
                'calculate' => url('/api/v1/campaigns/'.$campaign->uuid.'/checkout/calculate'),
                'coupon_validate' => url('/api/v1/coupons/validate'),
            ],
        ];

        $payload['variables'] = $this->variables->buildContext($campaign, $payload);
        $payload['campaign_uuid'] = $campaign->uuid;

        return $payload;
    }

    public function findCheckoutCampaign(string $uuid): Campaign
    {
        $campaign = Campaign::query()
            ->where('uuid', $uuid)
            ->whereIn('status', [CampaignStatus::Active, CampaignStatus::Draft])
            ->first();

        if ($campaign === null) {
            throw (new ModelNotFoundException)->setModel(Campaign::class, [$uuid]);
        }

        return $campaign;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function serializeProductLine(Campaign $campaign, CampaignProduct $line): ?array
    {
        $product = $line->product;

        if ($product === null) {
            return null;
        }

        $matrix = $this->variantMatrix->build($line);

        $variants = collect($matrix['variants'])
            ->map(function (array $variant) use ($line): array {
                $model = Variant::query()->where('uuid', $variant['uuid'])->first();

                return array_merge($variant, [
                    'billing_product' => $model
                        ? $this->pricing->resolveBillingProduct($line, $model)
                        : null,
                ]);
            })
            ->all();

        $metadata = (array) ($product->metadata ?? []);
        $audience = (string) ($metadata['audience'] ?? '');

        return [
            'line_id' => $line->id,
            'uuid' => $product->uuid,
            'sku' => $product->sku,
            'name' => $product->name,
            'audience' => $audience !== '' ? $audience : (string) ($metadata['legacy_gender'] ?? ''),
            'gender' => (string) ($metadata['legacy_gender'] ?? match ($audience) {
                'womens' => 'w',
                'mens' => 'm',
                default => '',
            }),
            'description' => $product->description,
            'short_description' => $product->short_description,
            'images' => array_values(array_filter((array) ($product->images ?? []))),
            'campaign_price' => $line->campaign_price !== null
                ? number_format((float) $line->campaign_price, 2, '.', '')
                : null,
            'min_quantity' => $line->min_quantity,
            'max_quantity' => $line->max_quantity,
            'colors' => $matrix['colors'],
            'sizes' => $matrix['sizes'],
            'matrix' => $matrix['matrix'],
            'variants' => $variants,
            'billing_cycles' => $line->billingCycles
                ->map(fn ($cycle): array => [
                    'cycle_number' => $cycle->cycle_number,
                    'product_uuid' => $cycle->product?->uuid,
                    'variant_uuid' => $cycle->variant?->uuid,
                    'price' => $cycle->price !== null ? number_format((float) $cycle->price, 2, '.', '') : null,
                    'shipping_price' => $cycle->shipping_price !== null ? number_format((float) $cycle->shipping_price, 2, '.', '') : null,
                    'subscription_enabled' => (bool) $cycle->subscription_enabled,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function serializeSubscriptionPlan(Campaign $campaign): ?array
    {
        $plan = $campaign->subscriptionPlan;

        if ($plan === null) {
            return null;
        }

        return [
            'uuid' => $plan->uuid,
            'name' => $plan->name,
            'currency' => strtoupper((string) ($plan->currency ?: $campaign->currency ?: 'USD')),
            'payment_gateway_id' => $plan->payment_gateway_id,
            'steps' => $plan->steps
                ->map(fn ($step): array => [
                    'step_order' => $step->step_order,
                    'amount' => number_format((float) $step->amount, 2, '.', ''),
                    'delay_days' => $step->delay_days,
                ])
                ->values()
                ->all(),
        ];
    }

    public function renderLandingPageHtml(Campaign $campaign, LandingPage $page, array $context): string
    {
        return $this->variables->replace((string) $page->content_html, $context);
    }
}
