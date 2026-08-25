<?php



namespace App\Modules\Campaign\Services;



use App\Models\Campaign;

use App\Models\CampaignProduct;

use App\Models\Product;

use App\Models\SubscriptionPlan;

use App\Models\SubscriptionPlanStep;

use App\Modules\Campaign\Enums\CampaignProductInventoryRule;

use App\Modules\Subscription\Enums\BillingCycleType;

use App\Modules\Subscription\Enums\BillingStepType;

use Illuminate\Support\Collection;



final class SubscriptionPlanCampaignBillingService

{

    public function __construct(

        private readonly CampaignProductBillingCycleService $billingCycles,

    ) {}



    public function campaignUsesPlan(Campaign $campaign): bool

    {

        return filled($campaign->subscription_plan_id);

    }



    /**

     * @return list<array<string, mixed>>

     */

    public function buildTemplateRows(CampaignProduct $campaignProduct): array

    {

        $campaignProduct->loadMissing(['campaign.subscriptionPlan.steps', 'product.variants']);



        $campaign = $campaignProduct->campaign;

        $plan = $campaign?->subscriptionPlan;



        if ($plan === null) {

            return [];

        }



        $steps = $plan->steps->sortBy('step_order')->values();

        $lastStepOrder = (int) ($steps->last()?->step_order ?? 0);

        $defaultVariant = $campaignProduct->product->variants->firstWhere('is_default', true)

            ?? $campaignProduct->product->variants->first();



        return $steps

            ->map(function (SubscriptionPlanStep $step) use (

                $campaignProduct,

                $defaultVariant,

                $lastStepOrder,

                $steps,

            ): array {

                $cycleNumber = (int) $step->step_order;

                $billDelayDays = $this->billDelayDays($step, $cycleNumber, $steps);



                return [

                    'cycle_number' => $cycleNumber,

                    'product_id' => $campaignProduct->product_id,

                    'variant_id' => $campaignProduct->variant_id ?? $defaultVariant?->id,

                    'price' => $step->amount,

                    'shipping_price' => null,

                    'bill_delay_days' => $billDelayDays,

                    'delay_fulfillment' => $billDelayDays > 0,

                    'shippable' => (bool) $campaignProduct->product->requires_shipping,

                    'warehouse' => null,

                    'subscription_enabled' => $cycleNumber === 1,

                    'repeat_forever' => $cycleNumber === $lastStepOrder

                        && $step->billing_type === BillingStepType::Recurring,

                    'inventory_rule' => CampaignProductInventoryRule::Inherit->value,

                    'inventory_quantity' => null,

                    'coupon_override' => null,

                ];

            })

            ->values()

            ->all();

    }



    public function inheritForCampaignProduct(CampaignProduct $campaignProduct): int

    {

        $campaignProduct->loadMissing('campaign');



        if (! $this->campaignUsesPlan($campaignProduct->campaign)) {

            return $this->billingCycles->inheritFromProductDefaults($campaignProduct);

        }



        $this->syncProductSubscriptionLink($campaignProduct);



        return $this->billingCycles->syncCycles(

            $campaignProduct,

            $this->buildTemplateRows($campaignProduct),

        );

    }



    /**

     * Re-apply the campaign plan to every line item, preserving per-cycle bill product SKUs.

     */

    public function applyPlanToCampaign(Campaign $campaign): int

    {

        if (! $this->campaignUsesPlan($campaign)) {

            return 0;

        }



        $campaign->loadMissing(['subscriptionPlan.steps', 'campaignProducts.billingCycles', 'campaignProducts.product']);



        $updated = 0;



        foreach ($campaign->campaignProducts as $campaignProduct) {

            $existingSkus = $campaignProduct->billingCycles

                ->keyBy('cycle_number')

                ->map(fn ($cycle): array => [

                    'product_id' => $cycle->product_id,

                    'variant_id' => $cycle->variant_id,

                ]);



            $rows = $this->buildTemplateRows($campaignProduct);



            foreach ($rows as &$row) {

                $existing = $existingSkus->get($row['cycle_number']);



                if ($existing !== null) {

                    $row['product_id'] = $existing['product_id'];

                    $row['variant_id'] = $existing['variant_id'];

                }

            }

            unset($row);



            $this->syncProductSubscriptionLink($campaignProduct);

            $this->billingCycles->syncCycles($campaignProduct, $rows);

            $updated++;

        }



        return $updated;

    }



    public function assignPlanToCampaign(SubscriptionPlan $plan, ?int $campaignId): void

    {

        Campaign::query()

            ->where('subscription_plan_id', $plan->id)

            ->when(

                $campaignId !== null,

                fn ($query) => $query->where('id', '!=', $campaignId),

            )

            ->update(['subscription_plan_id' => null]);



        if ($campaignId === null) {

            return;

        }



        /** @var Campaign $campaign */

        $campaign = Campaign::query()->findOrFail($campaignId);
        $campaign->update(['subscription_plan_id' => $plan->id]);
    }



    public function linkedCampaignId(SubscriptionPlan $plan): ?int

    {

        return Campaign::query()

            ->where('subscription_plan_id', $plan->id)

            ->value('id');

    }



    /**

     * @param  list<array<string, mixed>>  $skuRows

     */

    public function syncSkuOverrides(CampaignProduct $campaignProduct, array $skuRows): int

    {

        $template = $this->buildTemplateRows($campaignProduct);



        if ($template === []) {

            return 0;

        }



        $overrides = collect($skuRows)->keyBy('cycle_number');



        foreach ($template as &$row) {

            $override = $overrides->get($row['cycle_number']);



            if ($override === null) {

                continue;

            }



            $productId = (int) ($override['product_id'] ?? $row['product_id']);

            Product::query()->findOrFail($productId);



            $row['product_id'] = $productId;

            $row['variant_id'] = $override['variant_id'] ?? null;

        }

        unset($row);



        return $this->billingCycles->syncCycles($campaignProduct, $template);

    }



    private function syncProductSubscriptionLink(CampaignProduct $campaignProduct): void

    {

        $campaignProduct->loadMissing('campaign', 'product');



        $planId = $campaignProduct->campaign?->subscription_plan_id;



        if ($planId === null) {

            return;

        }



        $campaignProduct->product->update([

            'is_subscription' => true,

            'subscription_plan_id' => $planId,

        ]);

    }



    /**

     * @param  Collection<int, SubscriptionPlanStep>  $steps

     */

    private function billDelayDays(SubscriptionPlanStep $step, int $cycleNumber, Collection $steps): int

    {

        if ($cycleNumber === 1) {

            return 0;

        }



        if ($step->days_from_start !== null && (int) $step->days_from_start > 0) {

            return (int) $step->days_from_start;

        }



        if ($step->interval_days !== null && (int) $step->interval_days > 0) {

            return (int) $step->interval_days;

        }



        $cycleType = $step->cycle_type instanceof BillingCycleType

            ? $step->cycle_type

            : BillingCycleType::tryFrom((string) $step->cycle_type);



        return match ($cycleType) {

            BillingCycleType::Day7Rebill, BillingCycleType::Weekly => 7,

            BillingCycleType::Day30Rebill, BillingCycleType::Monthly => 30,

            BillingCycleType::Trial => max(0, (int) ($step->days_from_start ?? 0)),

            BillingCycleType::Custom => max(0, (int) ($step->interval_days ?? 30)),

            default => 10,

        };

    }

}

