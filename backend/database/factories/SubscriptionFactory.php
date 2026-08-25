<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Modules\Subscription\Enums\RebillQueueStatus;
use App\Modules\Subscription\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'customer_id' => Customer::factory(),
            'subscription_plan_id' => SubscriptionPlan::factory()->checkoutChampTrialContinuity(),
            'current_cycle_step' => 1,
            'status' => SubscriptionStatus::Trial,
            'currency' => 'USD',
            'amount' => 29.99,
            'billing_interval' => 'custom',
            'billing_interval_count' => 1,
            'billing_cycle_count' => 0,
            'payment_token' => 'pm_test_'.Str::random(8),
            'payment_provider' => 'stripe',
            'rebill_queue_status' => RebillQueueStatus::Queued,
            'started_at' => now(),
            'next_billing_at' => now()->addDays(7),
            'current_period_starts_at' => now(),
            'current_period_ends_at' => now()->addDays(7),
        ];
    }

    public function dueNow(): static
    {
        return $this->state(fn (): array => [
            'status' => SubscriptionStatus::Trial,
            'next_billing_at' => now()->subMinute(),
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => SubscriptionStatus::Active,
            'current_cycle_step' => 2,
            'billing_cycle_count' => 1,
            'next_billing_at' => now()->addDays(7),
        ]);
    }
}
