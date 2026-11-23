<?php

namespace Tests\Unit\Analytics;

use App\Models\BillingLedgerEntry;
use App\Models\Campaign;
use App\Models\Subscription;
use App\Modules\Admin\Data\DashboardFilter;
use App\Modules\Analytics\Services\RebillRecoveryAnalyticsService;
use App\Modules\Subscription\Enums\BillingLedgerOutcome;
use App\Modules\Subscription\Enums\SubscriptionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RebillRecoveryAnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_counts_first_attempt_success_and_retry_recovery_separately(): void
    {
        $subscription = Subscription::factory()->create([
            'current_cycle_step' => 2,
            'retry_count' => 0,
        ]);

        $another = Subscription::factory()->create([
            'current_cycle_step' => 2,
            'retry_count' => 0,
        ]);

        $at = Carbon::now()->subDays(2);

        BillingLedgerEntry::query()->create([
            'subscription_id' => $subscription->id,
            'cycle_step' => 2,
            'retry_attempt' => 0,
            'outcome' => BillingLedgerOutcome::Success,
            'amount' => 29.99,
            'currency' => 'USD',
            'idempotency_key' => 'sub-success-1',
            'attempted_at' => $at,
        ]);

        BillingLedgerEntry::query()->create([
            'subscription_id' => $another->id,
            'cycle_step' => 2,
            'retry_attempt' => 1,
            'outcome' => BillingLedgerOutcome::Failure,
            'amount' => 29.99,
            'currency' => 'USD',
            'idempotency_key' => 'sub-fail-1',
            'failure_code' => 'insufficient_funds',
            'attempted_at' => $at->copy()->addHour(),
        ]);

        BillingLedgerEntry::query()->create([
            'subscription_id' => $another->id,
            'cycle_step' => 2,
            'retry_attempt' => 1,
            'outcome' => BillingLedgerOutcome::Success,
            'amount' => 29.99,
            'currency' => 'USD',
            'idempotency_key' => 'sub-success-retry-1',
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

        $this->assertSame(2, $snapshot['kpis']['total_due']);
        $this->assertSame(1, $snapshot['kpis']['successful_first_attempt']);
        $this->assertSame(1, $snapshot['kpis']['recovered_after_retry']);
        $this->assertSame(100.0, $snapshot['rates']['retry_recovery_rate']);
        $this->assertCount(2, $snapshot['rows']);
    }

    #[Test]
    public function kpi_filter_limits_report_rows_but_not_aggregate_kpis(): void
    {
        $subscription = Subscription::factory()->create(['current_cycle_step' => 2]);
        $failed = Subscription::factory()->create([
            'current_cycle_step' => 2,
            'status' => SubscriptionStatus::PastDue,
            'retry_count' => 1,
        ]);

        $at = Carbon::now()->subDay();

        foreach ([$subscription, $failed] as $index => $sub) {
            BillingLedgerEntry::query()->create([
                'subscription_id' => $sub->id,
                'cycle_step' => 2,
                'retry_attempt' => $index === 0 ? 0 : 1,
                'outcome' => $index === 0 ? BillingLedgerOutcome::Success : BillingLedgerOutcome::Failure,
                'amount' => 10,
                'currency' => 'USD',
                'idempotency_key' => 'kpi-filter-'.$sub->id,
                'attempted_at' => $at,
            ]);
        }

        $filter = new DashboardFilter(
            from: now()->subDays(3),
            to: now()->addDay(),
            previousFrom: now()->subDays(6),
            previousTo: now()->subDays(3),
            currency: 'USD',
        );

        $service = app(RebillRecoveryAnalyticsService::class);
        $snapshot = $service->snapshot($filter, 'success_first');

        $this->assertSame(2, $snapshot['kpis']['total_due']);
        $this->assertCount(1, $snapshot['rows']);
    }

    #[Test]
    public function open_retry_cycle_is_not_marked_unrecovered(): void
    {
        $subscription = Subscription::factory()->create([
            'current_cycle_step' => 3,
            'status' => SubscriptionStatus::PastDue,
            'retry_count' => 1,
        ]);

        BillingLedgerEntry::query()->create([
            'subscription_id' => $subscription->id,
            'cycle_step' => 3,
            'retry_attempt' => 1,
            'outcome' => BillingLedgerOutcome::Failure,
            'amount' => 19.99,
            'currency' => 'USD',
            'idempotency_key' => 'open-cycle-fail',
            'attempted_at' => now()->subDay(),
        ]);

        $filter = new DashboardFilter(
            from: now()->subDays(3),
            to: now()->addDay(),
            previousFrom: now()->subDays(6),
            previousTo: now()->subDays(3),
            currency: 'USD',
        );

        $snapshot = app(RebillRecoveryAnalyticsService::class)->snapshot($filter);
        $row = $snapshot['rows'][0];

        $this->assertSame(RebillRecoveryAnalyticsService::STATUS_IN_PROGRESS, $row['final_status_key']);
        $this->assertSame(0, $snapshot['kpis']['permanently_unrecovered']);
        $this->assertSame(1, $snapshot['kpis']['recovery_in_progress']);
        $this->assertSame(0.0, $snapshot['kpis']['revenue_lost_unrecovered']);
    }

    #[Test]
    public function campaign_filter_includes_full_cycle_attempt_history(): void
    {
        $campaign = Campaign::factory()->create();
        $other = Campaign::factory()->create();

        $included = Subscription::factory()->create([
            'platform_campaign_id' => $campaign->id,
            'current_cycle_step' => 2,
        ]);
        $excluded = Subscription::factory()->create([
            'platform_campaign_id' => $other->id,
            'current_cycle_step' => 2,
        ]);

        $at = Carbon::now()->subDay();

        foreach ([$included, $excluded] as $sub) {
            BillingLedgerEntry::query()->create([
                'subscription_id' => $sub->id,
                'cycle_step' => 2,
                'retry_attempt' => 1,
                'outcome' => BillingLedgerOutcome::Failure,
                'amount' => 12,
                'currency' => 'USD',
                'idempotency_key' => 'camp-f-'.$sub->id,
                'attempted_at' => $at,
            ]);
            BillingLedgerEntry::query()->create([
                'subscription_id' => $sub->id,
                'cycle_step' => 2,
                'retry_attempt' => 1,
                'outcome' => BillingLedgerOutcome::Success,
                'amount' => 12,
                'currency' => 'USD',
                'idempotency_key' => 'camp-s-'.$sub->id,
                'attempted_at' => $at->copy()->addHour(),
            ]);
        }

        $filter = new DashboardFilter(
            from: now()->subDays(3),
            to: now()->addDay(),
            previousFrom: now()->subDays(6),
            previousTo: now()->subDays(3),
            currency: 'USD',
            campaignId: $campaign->id,
        );

        $snapshot = app(RebillRecoveryAnalyticsService::class)->snapshot($filter);

        $this->assertCount(1, $snapshot['rows']);
        $this->assertCount(2, $snapshot['rows'][0]['attempts']);
        $this->assertSame($campaign->name, $snapshot['rows'][0]['campaign']);
        $this->assertSame(12.0, $snapshot['rows'][0]['recovered_revenue']);
    }

    #[Test]
    public function report_metadata_matches_cycle_and_row_counts(): void
    {
        $at = Carbon::now()->subDay();
        $subs = Subscription::factory()->count(30)->create(['current_cycle_step' => 2]);

        foreach ($subs as $sub) {
            BillingLedgerEntry::query()->create([
                'subscription_id' => $sub->id,
                'cycle_step' => 2,
                'retry_attempt' => 0,
                'outcome' => BillingLedgerOutcome::Success,
                'amount' => 10,
                'currency' => 'USD',
                'idempotency_key' => 'meta-'.$sub->id,
                'attempted_at' => $at->copy()->addMinutes($sub->id),
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

        $this->assertSame(30, $snapshot['kpis']['total_due']);
        $this->assertCount(30, $snapshot['rows']);
        $this->assertSame(30, $snapshot['report']['cycles_in_period']);
        $this->assertSame(30, $snapshot['report']['filtered_row_count']);
    }

    #[Test]
    public function it_tracks_second_and_third_payment_sequence_successes(): void
    {
        $subscription = Subscription::factory()->create([
            'current_cycle_step' => 3,
            'retry_count' => 0,
        ]);

        $at = Carbon::now()->subDays(2);

        BillingLedgerEntry::query()->create([
            'subscription_id' => $subscription->id,
            'cycle_step' => 2,
            'retry_attempt' => 1,
            'outcome' => BillingLedgerOutcome::Failure,
            'amount' => 25,
            'currency' => 'USD',
            'idempotency_key' => 'seq-fail-1',
            'attempted_at' => $at,
        ]);

        BillingLedgerEntry::query()->create([
            'subscription_id' => $subscription->id,
            'cycle_step' => 2,
            'retry_attempt' => 1,
            'outcome' => BillingLedgerOutcome::Success,
            'amount' => 25,
            'currency' => 'USD',
            'idempotency_key' => 'seq-success-2',
            'transaction_id' => 'SANDBOX-TXN-002',
            'attempted_at' => $at->copy()->addDay(),
        ]);

        BillingLedgerEntry::query()->create([
            'subscription_id' => $subscription->id,
            'cycle_step' => 3,
            'retry_attempt' => 0,
            'outcome' => BillingLedgerOutcome::Success,
            'amount' => 25,
            'currency' => 'USD',
            'idempotency_key' => 'seq-success-3',
            'transaction_id' => 'SANDBOX-TXN-003',
            'attempted_at' => $at->copy()->addDays(3),
        ]);

        $filter = new DashboardFilter(
            from: now()->subDays(7),
            to: now()->addDay(),
            previousFrom: now()->subDays(14),
            previousTo: now()->subDays(7),
            currency: 'USD',
        );

        $snapshot = app(RebillRecoveryAnalyticsService::class)->snapshot($filter);

        $this->assertSame(1, $snapshot['kpis']['recovered_after_retry']);
        $this->assertSame(1, $snapshot['kpis']['second_attempt_success']);
        $this->assertSame(1, $snapshot['kpis']['third_attempt_success'], 'Third rebill = next cycle collected after recovery');
        $this->assertCount(1, $snapshot['featured_journeys']);
        $this->assertCount(3, $snapshot['featured_journeys'][0]['timeline']);
        $this->assertNull($snapshot['showcase']);
    }

    #[Test]
    public function successful_retry_is_not_counted_as_failed_first_attempt_only(): void
    {
        $subscription = Subscription::factory()->create(['current_cycle_step' => 2]);
        $at = Carbon::now()->subDay();

        BillingLedgerEntry::query()->create([
            'subscription_id' => $subscription->id,
            'cycle_step' => 2,
            'retry_attempt' => 1,
            'outcome' => BillingLedgerOutcome::Failure,
            'amount' => 10,
            'currency' => 'USD',
            'idempotency_key' => 'not-fail-kpi-1',
            'attempted_at' => $at,
        ]);

        BillingLedgerEntry::query()->create([
            'subscription_id' => $subscription->id,
            'cycle_step' => 2,
            'retry_attempt' => 1,
            'outcome' => BillingLedgerOutcome::Success,
            'amount' => 10,
            'currency' => 'USD',
            'idempotency_key' => 'not-fail-kpi-2',
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

        $this->assertSame(1, $snapshot['kpis']['failed_first_attempt']);
        $this->assertSame(1, $snapshot['kpis']['recovered_after_retry']);
        $this->assertSame(0, $snapshot['kpis']['permanently_unrecovered']);
        $this->assertSame(10.0, $snapshot['recovery_sequence']['total_recovered_amount']);
    }

    #[Test]
    public function showcase_is_separate_from_live_kpis_when_enabled_in_local(): void
    {
        config([
            'rebill_recovery.showcase.enabled' => true,
            'rebill_recovery.showcase.allow_outside_local' => false,
        ]);
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
        $this->assertTrue($snapshot['showcase']['is_simulated']);
        $this->assertSame(0, $snapshot['kpis']['total_due']);
    }
}
