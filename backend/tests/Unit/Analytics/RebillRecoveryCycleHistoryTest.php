<?php

namespace Tests\Unit\Analytics;

use App\Models\BillingLedgerEntry;
use App\Models\Subscription;
use App\Modules\Admin\Data\DashboardFilter;
use App\Modules\Analytics\Services\RebillRecoveryAnalyticsService;
use App\Modules\Analytics\Services\RebillRecoveryCycleHistoryPresenter;
use App\Modules\Analytics\Services\RebillRecoveryShowcaseFixture;
use App\Modules\Subscription\Enums\BillingLedgerOutcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RebillRecoveryCycleHistoryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function multiple_retry_attempts_share_one_rebill_cycle(): void
    {
        $subscription = Subscription::factory()->create(['current_cycle_step' => 2]);
        $at = Carbon::now()->subDay();

        foreach (['a', 'b', 'c'] as $suffix) {
            BillingLedgerEntry::query()->create([
                'subscription_id' => $subscription->id,
                'cycle_step' => 2,
                'retry_attempt' => 1,
                'outcome' => $suffix === 'c' ? BillingLedgerOutcome::Success : BillingLedgerOutcome::Failure,
                'amount' => 15,
                'currency' => 'USD',
                'idempotency_key' => 'same-cycle-'.$suffix,
                'attempted_at' => $at->copy()->addHours((int) strlen($suffix)),
            ]);
        }

        $filter = new DashboardFilter(
            from: now()->subDays(3),
            to: now()->addDay(),
            previousFrom: now()->subDays(6),
            previousTo: now()->subDays(3),
            currency: 'USD',
        );

        $snapshot = app(RebillRecoveryAnalyticsService::class)->snapshot($filter);

        $this->assertSame(1, $snapshot['cycle_analytics']['total_cycles']);
        $this->assertSame(3, $snapshot['attempt_analytics']['total_retry_attempts']);
        $this->assertCount(3, $snapshot['subscription_histories'][0]['rebill_cycles'][0]['retry_attempts']);
    }

    #[Test]
    public function successful_retry_does_not_create_a_new_cycle(): void
    {
        $subscription = Subscription::factory()->create(['current_cycle_step' => 3]);
        $at = Carbon::now()->subDays(2);

        BillingLedgerEntry::query()->create([
            'subscription_id' => $subscription->id,
            'cycle_step' => 2,
            'retry_attempt' => 1,
            'outcome' => BillingLedgerOutcome::Failure,
            'amount' => 20,
            'currency' => 'USD',
            'idempotency_key' => 'cycle2-fail',
            'attempted_at' => $at,
        ]);

        BillingLedgerEntry::query()->create([
            'subscription_id' => $subscription->id,
            'cycle_step' => 2,
            'retry_attempt' => 1,
            'outcome' => BillingLedgerOutcome::Success,
            'amount' => 20,
            'currency' => 'USD',
            'idempotency_key' => 'cycle2-success',
            'attempted_at' => $at->copy()->addHour(),
        ]);

        BillingLedgerEntry::query()->create([
            'subscription_id' => $subscription->id,
            'cycle_step' => 3,
            'retry_attempt' => 0,
            'outcome' => BillingLedgerOutcome::Success,
            'amount' => 20,
            'currency' => 'USD',
            'idempotency_key' => 'cycle3-success',
            'attempted_at' => $at->copy()->addDay(),
        ]);

        $filter = new DashboardFilter(
            from: now()->subDays(7),
            to: now()->addDay(),
            previousFrom: now()->subDays(14),
            previousTo: now()->subDays(7),
            currency: 'USD',
        );

        $snapshot = app(RebillRecoveryAnalyticsService::class)->snapshot($filter);

        $this->assertSame(2, $snapshot['cycle_analytics']['total_cycles']);
        $this->assertSame(2, $snapshot['cycle_analytics']['successful_cycles']);
        $history = $snapshot['subscription_histories'][0]['rebill_cycles'];
        $this->assertCount(2, $history);
        $this->assertSame(2, $history[0]['cycle_number']);
        $this->assertSame(3, $history[1]['cycle_number']);
    }

    #[Test]
    public function cycle_and_attempt_counters_are_computed_separately(): void
    {
        $subSuccess = Subscription::factory()->create(['current_cycle_step' => 2]);
        $subFailed = Subscription::factory()->create([
            'current_cycle_step' => 3,
            'status' => \App\Modules\Subscription\Enums\SubscriptionStatus::PastDue,
            'retry_count' => 3,
        ]);

        $at = Carbon::now()->subDay();

        BillingLedgerEntry::query()->create([
            'subscription_id' => $subSuccess->id,
            'cycle_step' => 2,
            'retry_attempt' => 0,
            'outcome' => BillingLedgerOutcome::Success,
            'amount' => 10,
            'currency' => 'USD',
            'idempotency_key' => 'ca-ok',
            'attempted_at' => $at,
        ]);

        BillingLedgerEntry::query()->create([
            'subscription_id' => $subFailed->id,
            'cycle_step' => 3,
            'retry_attempt' => 1,
            'outcome' => BillingLedgerOutcome::Failure,
            'amount' => 12,
            'currency' => 'USD',
            'idempotency_key' => 'ca-f1',
            'attempted_at' => $at,
        ]);

        BillingLedgerEntry::query()->create([
            'subscription_id' => $subFailed->id,
            'cycle_step' => 3,
            'retry_attempt' => 2,
            'outcome' => BillingLedgerOutcome::Failure,
            'amount' => 12,
            'currency' => 'USD',
            'idempotency_key' => 'ca-f2',
            'attempted_at' => $at->copy()->addHour(),
        ]);

        $filter = new DashboardFilter(
            from: now()->subDays(3),
            to: now()->addDay(),
            previousFrom: now()->subDays(6),
            previousTo: now()->subDays(3),
            currency: 'USD',
        );

        $snapshot = app(RebillRecoveryAnalyticsService::class)->snapshot($filter);

        $this->assertSame(2, $snapshot['cycle_analytics']['total_cycles']);
        $this->assertSame(1, $snapshot['cycle_analytics']['successful_cycles']);
        $this->assertSame(1, $snapshot['cycle_analytics']['failed_cycles']);
        $this->assertSame(3, $snapshot['attempt_analytics']['total_retry_attempts']);
        $this->assertSame(1, $snapshot['attempt_analytics']['successful_retry_attempts']);
        $this->assertSame(2, $snapshot['attempt_analytics']['failed_retry_attempts']);
    }

    #[Test]
    public function showcase_fixture_does_not_contaminate_live_analytics(): void
    {
        config(['rebill_recovery.showcase.enabled' => true]);
        $this->app->detectEnvironment(fn () => 'local');

        $filter = new DashboardFilter(
            from: now()->subDays(3),
            to: now()->addDay(),
            previousFrom: now()->subDays(6),
            previousTo: now()->subDays(3),
            currency: 'USD',
        );

        $snapshot = app(RebillRecoveryAnalyticsService::class)->snapshot($filter);

        $this->assertNotNull($snapshot['showcase']);
        $this->assertTrue($snapshot['showcase']['cycle_analytics']['is_simulated']);
        $this->assertArrayNotHasKey('is_simulated', $snapshot['cycle_analytics']);
        $this->assertSame(0, $snapshot['cycle_analytics']['total_cycles']);
    }

    #[Test]
    public function showcase_nested_cycles_include_required_scenarios(): void
    {
        $payload = app(RebillRecoveryShowcaseFixture::class)->build();
        $cycles = $payload['subscription_histories'][0]['rebill_cycles'];

        $this->assertCount(3, $cycles);
        $this->assertSame('successful', $cycles[0]['cycle_status']);
        $this->assertCount(3, $cycles[0]['retry_attempts']);
        $this->assertSame('successful', $cycles[1]['cycle_status']);
        $this->assertCount(1, $cycles[1]['retry_attempts']);
        $this->assertSame('failed', $cycles[2]['cycle_status']);
    }

    #[Test]
    public function presenter_maps_cycle_status_from_internal_state(): void
    {
        $subscription = Subscription::factory()->create();
        $entry = BillingLedgerEntry::query()->create([
            'subscription_id' => $subscription->id,
            'cycle_step' => 1,
            'retry_attempt' => 0,
            'outcome' => BillingLedgerOutcome::Success,
            'amount' => 5,
            'currency' => 'USD',
            'idempotency_key' => 'presenter-1',
            'attempted_at' => now(),
        ]);

        $cycle = [
            'cycle_key' => $subscription->id.':1',
            'subscription' => $subscription->load(['customer', 'paymentGateway', 'platformCampaign']),
            'cycle_step' => 1,
            'entries' => [$entry],
            'scheduled_amount' => 5,
            'currency' => 'USD',
            'status' => RebillRecoveryAnalyticsService::STATUS_SUCCESS_FIRST,
            'success_entry' => $entry,
            'first_entry' => $entry,
            'collected_amount' => 5,
        ];

        $block = app(RebillRecoveryCycleHistoryPresenter::class)->formatCycleBlock($cycle);

        $this->assertSame(1, $block['cycle_number']);
        $this->assertSame('successful', $block['cycle_status']);
        $this->assertCount(1, $block['retry_attempts']);
    }
}