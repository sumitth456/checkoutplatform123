<?php

namespace Database\Factories;

use App\Models\DunningPolicy;
use App\Models\RetryPolicy;
use App\Models\SubscriptionPlan;
use App\Modules\Subscription\Enums\BillingCycleType;
use App\Modules\Subscription\Enums\BillingStepType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SubscriptionPlan>
 */
class SubscriptionPlanFactory extends Factory
{
    protected $model = SubscriptionPlan::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'name' => fake()->words(3, true),
            'slug' => fake()->unique()->slug(),
            'currency' => 'USD',
            'retry_policy_id' => RetryPolicy::factory(),
            'dunning_policy_id' => DunningPolicy::factory(),
            'is_active' => true,
            'metadata' => [],
        ];
    }

    public function checkoutChampTrialContinuity(): static
    {
        return $this->afterCreating(function (SubscriptionPlan $plan): void {
            if ($plan->steps()->exists()) {
                return;
            }

            $plan->steps()->createMany([
                [
                    'step_order' => 1,
                    'billing_type' => BillingStepType::OneTime->value,
                    'cycle_type' => BillingCycleType::Trial->value,
                    'days_from_start' => 7,
                    'amount' => 0,
                    'currency' => 'USD',
                ],
                [
                    'step_order' => 2,
                    'billing_type' => BillingStepType::OneTime->value,
                    'cycle_type' => BillingCycleType::Day7Rebill->value,
                    'days_from_start' => 7,
                    'amount' => 29.99,
                    'currency' => 'USD',
                ],
                [
                    'step_order' => 3,
                    'billing_type' => BillingStepType::OneTime->value,
                    'cycle_type' => BillingCycleType::Day30Rebill->value,
                    'days_from_start' => 30,
                    'amount' => 49.99,
                    'currency' => 'USD',
                ],
                [
                    'step_order' => 4,
                    'billing_type' => BillingStepType::Recurring->value,
                    'cycle_type' => BillingCycleType::Monthly->value,
                    'interval_days' => 30,
                    'amount' => 49.99,
                    'currency' => 'USD',
                ],
            ]);
        });
    }

    public function checkoutChampTestProductPlan(): static
    {
        return $this->state(fn (): array => [
            'name' => 'Test Subscription Plan',
            'slug' => 'test-subscription-plan-'.Str::lower(Str::random(6)),
        ])->afterCreating(function (SubscriptionPlan $plan): void {
            if ($plan->steps()->exists()) {
                return;
            }

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
        });
    }
}
