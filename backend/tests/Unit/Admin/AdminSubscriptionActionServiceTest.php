<?php

namespace Tests\Unit\Admin;

use App\Models\Customer;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Modules\Admin\Services\AdminSubscriptionActionService;
use App\Modules\Subscription\Enums\SubscriptionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSubscriptionActionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_reschedule_next_billing_updates_subscription(): void
    {
        $plan = SubscriptionPlan::factory()->create();
        $customer = Customer::factory()->create();

        $subscription = Subscription::factory()->create([
            'customer_id' => $customer->id,
            'subscription_plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'next_billing_at' => now()->addWeek(),
        ]);

        $next = now()->addDays(3);

        app(AdminSubscriptionActionService::class)->rescheduleNextBilling($subscription, $next);

        $subscription->refresh();

        $this->assertSame(
            $next->toDateTimeString(),
            $subscription->next_billing_at->toDateTimeString(),
        );
        $this->assertDatabaseHas('admin_activity_logs', [
            'module' => 'subscriptions',
            'event' => 'rescheduled',
            'subject_type' => $subscription->getMorphClass(),
            'subject_id' => $subscription->id,
        ]);
    }
}
