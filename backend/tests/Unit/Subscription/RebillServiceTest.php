<?php

namespace Tests\Unit\Subscription;

use App\Models\PaymentGatewayConfig;
use App\Models\Subscription;
use App\Modules\Subscription\Enums\BillingLedgerOutcome;
use App\Modules\Subscription\Enums\SubscriptionStatus;
use App\Modules\Subscription\Events\SubscriptionExpired;
use App\Modules\Subscription\Events\SubscriptionRebillFailed;
use App\Modules\Subscription\Events\SubscriptionRebilled;
use App\Modules\Subscription\Services\RebillService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RebillServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        PaymentGatewayConfig::query()->create([
            'name' => 'Stripe Test',
            'slug' => 'stripe-rebill-test',
            'provider' => 'stripe',
            'credentials' => ['secret_key' => 'sk_test'],
            'is_active' => true,
            'is_default' => true,
            'supports_subscriptions' => true,
            'failover_enabled' => true,
            'sort_order' => 1,
            'weight' => 100,
        ]);
    }

    #[Test]
    public function it_charges_trial_subscription_and_advances_to_active_recurring_cycle(): void
    {
        Http::fake([
            'api.stripe.com/*' => Http::sequence()
                ->push([
                    'id' => 'pi_sub_trial',
                    'client_secret' => 'secret',
                    'status' => 'requires_capture',
                ], 200)
                ->push([
                    'id' => 'pi_sub_trial',
                    'status' => 'succeeded',
                ], 200),
        ]);

        Event::fake([SubscriptionRebilled::class]);

        $subscription = Subscription::factory()->dueNow()->create();
        $subscription->load('plan.steps');

        app(RebillService::class)->charge($subscription->fresh(['plan.steps', 'plan.retryPolicy']));

        $subscription->refresh();

        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertSame(2, $subscription->current_cycle_step);
        $this->assertSame(0, $subscription->retry_count);
        $this->assertNotNull($subscription->next_billing_at);
        $this->assertTrue($subscription->next_billing_at->greaterThan(now()));

        $this->assertDatabaseHas('billing_ledger_entries', [
            'subscription_id' => $subscription->id,
            'outcome' => BillingLedgerOutcome::Success->value,
            'cycle_step' => 1,
        ]);

        Event::assertDispatched(SubscriptionRebilled::class);
    }

    #[Test]
    public function it_applies_retry_policy_on_failed_charge(): void
    {
        Http::fake([
            'api.stripe.com/*' => Http::response(['message' => 'card declined'], 402),
        ]);

        Event::fake([SubscriptionRebillFailed::class, SubscriptionExpired::class]);

        $subscription = Subscription::factory()->active()->create([
            'next_billing_at' => now()->subMinute(),
            'retry_count' => 0,
        ]);
        $subscription->load('plan.steps');

        app(RebillService::class)->charge($subscription->fresh(['plan.steps', 'plan.retryPolicy']));

        $subscription->refresh();

        $this->assertSame(SubscriptionStatus::PastDue, $subscription->status);
        $this->assertSame(1, $subscription->retry_count);
        $this->assertTrue($subscription->next_billing_at->greaterThan(now()));

        $this->assertDatabaseHas('billing_ledger_entries', [
            'subscription_id' => $subscription->id,
            'outcome' => BillingLedgerOutcome::Failure->value,
        ]);

        Event::assertDispatched(SubscriptionRebillFailed::class);
        Event::assertNotDispatched(SubscriptionExpired::class);
    }

    #[Test]
    public function it_cancels_subscription_when_retry_attempts_are_exhausted(): void
    {
        Http::fake([
            'api.stripe.com/*' => Http::response(['message' => 'card declined'], 402),
        ]);

        Event::fake([SubscriptionExpired::class, SubscriptionRebillFailed::class]);

        $subscription = Subscription::factory()->active()->create([
            'next_billing_at' => now()->subMinute(),
            'retry_count' => 3,
            'status' => SubscriptionStatus::PastDue,
        ]);
        $subscription->load('plan.steps');

        app(RebillService::class)->charge($subscription->fresh(['plan.steps', 'plan.retryPolicy']));

        $subscription->refresh();

        $this->assertSame(SubscriptionStatus::Cancelled, $subscription->status);
        $this->assertSame('retries_exhausted', $subscription->cancellation_reason);

        Event::assertDispatched(SubscriptionExpired::class);
    }

    #[Test]
    public function it_schedules_seven_day_retry_after_first_rebill_failure(): void
    {
        Http::fake([
            'api.stripe.com/*' => Http::response(['message' => 'card declined'], 402),
        ]);

        Event::fake([SubscriptionRebillFailed::class]);

        $subscription = Subscription::factory()->active()->create([
            'next_billing_at' => now()->subMinute(),
            'retry_count' => 0,
            'current_cycle_step' => 2,
            'billing_cycle_count' => 1,
        ]);
        $subscription->load('plan.steps');

        app(RebillService::class)->charge($subscription->fresh(['plan.steps', 'plan.retryPolicy']));

        $subscription->refresh();

        $this->assertSame(SubscriptionStatus::PastDue, $subscription->status);
        $this->assertSame(1, $subscription->retry_count);
        $this->assertTrue($subscription->next_billing_at->greaterThan(now()->addDays(6)));
        $this->assertTrue($subscription->next_billing_at->lessThan(now()->addDays(8)));
    }
}
