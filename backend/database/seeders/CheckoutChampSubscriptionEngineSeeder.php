<?php

namespace Database\Seeders;

use App\Models\DunningPolicy;
use App\Models\RetryPolicy;
use App\Models\SubscriptionPlan;
use App\Modules\Subscription\Enums\BillingCycleType;
use App\Modules\Subscription\Enums\BillingStepType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CheckoutChampSubscriptionEngineSeeder extends Seeder
{
    public function run(): void
    {
        $retry = RetryPolicy::query()->firstOrCreate(
            ['name' => 'CheckoutChamp Standard Retry'],
            [
                'uuid' => (string) Str::uuid(),
                'max_attempts' => 3,
                'retry_offsets' => [1, 3, 7],
            ],
        );

        $dunning = DunningPolicy::query()->firstOrCreate(
            ['name' => 'CheckoutChamp Dunning'],
            [
                'uuid' => (string) Str::uuid(),
                'is_active' => true,
                'stages' => [
                    ['day_offset' => 1, 'channel' => 'email', 'template' => 'payment_failed_day_1'],
                    ['day_offset' => 3, 'channel' => 'email', 'template' => 'payment_failed_day_3'],
                    ['day_offset' => 7, 'channel' => 'email', 'template' => 'payment_failed_day_7'],
                ],
            ],
        );

        $plan = SubscriptionPlan::query()->firstOrCreate(
            ['slug' => 'checkoutchamp-trial-7-30-monthly'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Trial · Day 7 · Day 30 · Monthly',
                'currency' => 'USD',
                'retry_policy_id' => $retry->id,
                'dunning_policy_id' => $dunning->id,
                'is_active' => true,
                'metadata' => [
                    'description' => 'CheckoutChamp-style trial with day 7 rebill, day 30 rebill, then monthly continuity',
                ],
            ],
        );

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
    }
}
