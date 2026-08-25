<?php

namespace Tests\Unit\Subscription;

use App\Models\Subscription;
use App\Models\SubscriptionPlanStep;
use App\Modules\Subscription\Enums\BillingStepType;
use App\Modules\Subscription\Enums\SubscriptionStatus;
use App\Modules\Subscription\Services\BillingCycleAdvancer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BillingCycleAdvancerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_advances_from_trial_one_time_step_to_recurring_step(): void
    {
        $subscription = Subscription::factory()->dueNow()->create([
            'status' => SubscriptionStatus::Trial,
            'current_cycle_step' => 1,
            'billing_cycle_count' => 0,
        ]);

        $subscription->load('plan.steps');
        $step = $subscription->plan->steps->firstWhere('step_order', 1);

        app(BillingCycleAdvancer::class)->advanceAfterSuccess($subscription, $step);
        $subscription->save();
        $subscription->refresh();

        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertSame(1, $subscription->billing_cycle_count);
        $this->assertSame(2, $subscription->current_cycle_step);
        $this->assertTrue($subscription->next_billing_at->greaterThan(now()->addDays(29)));
    }

    #[Test]
    public function it_advances_recurring_step_by_interval_days(): void
    {
        $subscription = Subscription::factory()->active()->create([
            'current_cycle_step' => 2,
            'billing_cycle_count' => 1,
            'next_billing_at' => now(),
        ]);

        $subscription->load('plan.steps');
        $step = $subscription->plan->steps->firstWhere('step_order', 2);

        app(BillingCycleAdvancer::class)->advanceAfterSuccess($subscription, $step);
        $subscription->save();
        $subscription->refresh();

        $this->assertSame(2, $subscription->billing_cycle_count);
        $this->assertSame(2, $subscription->current_cycle_step);
        $this->assertTrue($subscription->next_billing_at->greaterThan(now()->addDays(29)));
    }

    #[Test]
    public function it_restores_active_status_from_past_due_after_success(): void
    {
        $subscription = Subscription::factory()->active()->create([
            'status' => SubscriptionStatus::PastDue,
            'current_cycle_step' => 2,
            'billing_cycle_count' => 2,
        ]);

        $subscription->load('plan.steps');
        $step = $subscription->plan->steps->firstWhere('step_order', 2);

        app(BillingCycleAdvancer::class)->advanceAfterSuccess($subscription, $step);
        $subscription->save();
        $subscription->refresh();

        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
    }

    #[Test]
    public function it_clears_next_billing_when_no_further_steps_exist(): void
    {
        $subscription = Subscription::factory()->create([
            'status' => SubscriptionStatus::Active,
            'current_cycle_step' => 1,
            'billing_cycle_count' => 0,
        ]);

        $subscription->plan->steps()->delete();
        $subscription->plan->steps()->create([
            'step_order' => 1,
            'billing_type' => BillingStepType::OneTime->value,
            'days_from_start' => 0,
            'interval_days' => null,
            'amount' => 9.99,
            'currency' => 'USD',
        ]);

        $subscription->load('plan.steps');
        $step = $subscription->plan->steps->first();

        app(BillingCycleAdvancer::class)->advanceAfterSuccess($subscription, $step);
        $subscription->save();
        $subscription->refresh();

        $this->assertNull($subscription->next_billing_at);
    }
}
