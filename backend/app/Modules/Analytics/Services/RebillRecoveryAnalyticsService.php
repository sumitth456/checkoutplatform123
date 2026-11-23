<?php

namespace App\Modules\Analytics\Services;

use App\Models\BillingLedgerEntry;
use App\Models\Campaign;
use App\Models\PaymentGatewayConfig;
use App\Models\Subscription;
use App\Modules\Admin\Data\DashboardFilter;
use App\Modules\Subscription\Enums\BillingLedgerOutcome;
use App\Modules\Subscription\Enums\SubscriptionStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Rebill recovery metrics from billing_ledger_entries grouped by subscription + cycle_step.
 *
 * Attempt semantics (see RebillService): success rows use retry_attempt = retry_count before charge;
 * failure rows use retry_count + 1. Initial success => retry_attempt 0.
 */
final class RebillRecoveryAnalyticsService
{
    public function __construct(
        private readonly RebillRecoveryShowcaseFixture $showcaseFixture,
        private readonly RebillRecoveryCycleHistoryPresenter $cycleHistory,
    ) {}

    public const STATUS_SUCCESS_FIRST = 'successful_first_attempt';

    public const STATUS_RECOVERED_RETRY = 'recovered_on_retry';

    public const STATUS_UNRECOVERED = 'unrecovered';

    public const STATUS_IN_PROGRESS = 'recovery_in_progress';

    /** @return array<string, mixed> */
    public function snapshot(DashboardFilter $filter, ?string $kpiFilter = null, ?string $reportFilter = null): array
    {
        $cycles = $this->loadCycles($filter);
        $rowFilter = $reportFilter ?? $kpiFilter;
        $reportCycles = $this->applyKpiFilter($cycles, $rowFilter);

        $paymentAttemptStats = $this->paymentAttemptStatistics($cycles);
        $cycleAnalytics = $this->cycleHistory->cycleAnalytics($cycles);
        $attemptAnalytics = $this->cycleHistory->attemptAnalytics($cycles);

        return [
            'period' => [
                'from' => $filter->from->toIso8601String(),
                'to' => $filter->to->toIso8601String(),
                'currency' => $filter->currency,
            ],
            'limitations' => $this->limitations(),
            'kpis' => $this->computeKpis($cycles, $paymentAttemptStats),
            'cycle_analytics' => $cycleAnalytics,
            'attempt_analytics' => $attemptAnalytics,
            'subscription_histories' => $this->cycleHistory->subscriptionHistories($reportCycles),
            'recovery_by_attempt' => $this->recoveryByAttempt($cycles),
            'recovery_sequence' => $this->recoverySequenceSummary($cycles, $paymentAttemptStats),
            'payment_attempt_statistics' => $paymentAttemptStats,
            'rates' => $this->computeRates($cycles),
            'charts' => $this->buildCharts($cycles, $filter),
            'rows' => $this->buildReportRows($reportCycles),
            'featured_journeys' => $this->buildFeaturedRecoveryJourneys($cycles),
            'showcase' => $this->resolveShowcase(),
            'report' => [
                'cycles_in_period' => $cycles->count(),
                'filtered_row_count' => $reportCycles->count(),
                'active_status_filter' => $rowFilter,
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function resolveShowcase(): ?array
    {
        if (! config('rebill_recovery.showcase.enabled')) {
            return null;
        }

        if (! app()->environment('local') && ! config('rebill_recovery.showcase.allow_outside_local')) {
            return null;
        }

        return $this->showcaseFixture->build();
    }

    /** @return Collection<int, array<string, mixed>> */
    public function loadCycles(DashboardFilter $filter): Collection
    {
        $subQuery = $this->subscriptionFilteredSubquery($filter);

        $cycleKeys = BillingLedgerEntry::query()
            ->selectRaw('subscription_id, cycle_step, MIN(attempted_at) as first_attempt_at')
            ->whereIn('subscription_id', $subQuery)
            ->groupBy('subscription_id', 'cycle_step')
            ->havingBetween('first_attempt_at', [$filter->from, $filter->to])
            ->get();

        if ($cycleKeys->isEmpty()) {
            return collect();
        }

        $pairs = $cycleKeys->map(fn ($row) => [
            'subscription_id' => (int) $row->subscription_id,
            'cycle_step' => (int) $row->cycle_step,
        ]);

        $entries = $this->loadLedgerEntriesForPairs($pairs);

        return $entries
            ->groupBy(fn (BillingLedgerEntry $entry) => $entry->subscription_id.':'.$entry->cycle_step)
            ->map(fn (Collection $group) => $this->analyzeCycle($group));
    }

    /**
     * @param  Collection<int, array{subscription_id: int, cycle_step: int}>  $pairs
     * @return Collection<int, BillingLedgerEntry>
     */
    private function loadLedgerEntriesForPairs(Collection $pairs): Collection
    {
        if ($pairs->isEmpty()) {
            return collect();
        }

        $eager = [
            'subscription.customer',
            'subscription.order',
            'subscription.platformCampaign',
            'subscription.plan.retryPolicy',
            'subscription.paymentGateway',
        ];

        /** @var Collection<int, BillingLedgerEntry> $entries */
        $entries = collect();

        foreach ($pairs->chunk(100) as $chunk) {
            $entries = $entries->merge(
                BillingLedgerEntry::query()
                    ->with($eager)
                    ->where(function (Builder $query) use ($chunk): void {
                        foreach ($chunk as $pair) {
                            $query->orWhere(function (Builder $inner) use ($pair): void {
                                $inner->where('subscription_id', $pair['subscription_id'])
                                    ->where('cycle_step', $pair['cycle_step']);
                            });
                        }
                    })
                    ->orderBy('attempted_at')
                    ->get()
            );
        }

        return $entries
            ->unique('id')
            ->sortBy('attempted_at')
            ->values();
    }

    /** @param  Collection<int, BillingLedgerEntry>  $entries */
    private function analyzeCycle(Collection $entries): array
    {
        /** @var BillingLedgerEntry $first */
        $first = $entries->first();
        $subscription = $first->subscription;
        $success = $entries->first(fn (BillingLedgerEntry $e) => $e->outcome === BillingLedgerOutcome::Success);
        $firstFailed = $first->outcome === BillingLedgerOutcome::Failure;

        $maxAttempts = (int) ($subscription?->plan?->retryPolicy?->max_attempts ?? 0);

        $status = self::STATUS_UNRECOVERED;
        $recoveryAttemptNumber = null;

        if ($success) {
            $attemptIndex = (int) $success->retry_attempt;
            if ($attemptIndex === 0) {
                $status = self::STATUS_SUCCESS_FIRST;
                $recoveryAttemptNumber = null;
            } else {
                $status = self::STATUS_RECOVERED_RETRY;
                // Human charge attempt: success retry_attempt N => (N + 1)st charge (RebillService).
                $recoveryAttemptNumber = $attemptIndex + 1;
            }
        } elseif ($this->isCycleOpen($subscription, (int) $first->cycle_step, $maxAttempts)) {
            $status = self::STATUS_IN_PROGRESS;
        }

        $collectedAmount = $success ? (float) $success->amount : 0.0;
        $scheduledAmount = (float) ($entries->max('amount') ?? 0);
        $lostAmount = $status === self::STATUS_UNRECOVERED ? $scheduledAmount : 0.0;
        $recoveredAfterFailRevenue = ($status === self::STATUS_RECOVERED_RETRY && $success)
            ? (float) $success->amount
            : 0.0;

        return [
            'cycle_key' => $subscription?->id.':'.$first->cycle_step,
            'subscription' => $subscription,
            'cycle_step' => (int) $first->cycle_step,
            'entries' => $entries->values()->all(),
            'first_attempt_at' => $first->attempted_at,
            'scheduled_amount' => $scheduledAmount,
            'currency' => $first->currency,
            'initial_failed' => $firstFailed,
            'status' => $status,
            'recovery_attempt_number' => $recoveryAttemptNumber,
            'retry_attempt_count' => max(0, $entries->count() - 1),
            'collected_amount' => $collectedAmount,
            'recovered_retry_revenue' => $recoveredAfterFailRevenue,
            'lost_amount' => $lostAmount,
            'success_entry' => $success,
            'first_entry' => $first,
        ];
    }

    private function isCycleOpen(?Subscription $subscription, int $cycleStep, int $maxAttempts): bool
    {
        if ($subscription === null) {
            return false;
        }

        if ((int) $subscription->current_cycle_step !== $cycleStep) {
            return false;
        }

        if ($subscription->status !== SubscriptionStatus::PastDue) {
            return false;
        }

        if ($maxAttempts > 0 && (int) $subscription->retry_count >= $maxAttempts) {
            return false;
        }

        if ($subscription->status === SubscriptionStatus::Cancelled) {
            return false;
        }

        return true;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $cycles
     * @param  list<array<string, mixed>>|null  $paymentAttemptStats
     */
    private function computeKpis(Collection $cycles, ?array $paymentAttemptStats = null): array
    {
        $due = $cycles->count();
        $attempted = $due;
        $successFirst = $cycles->where('status', self::STATUS_SUCCESS_FIRST)->count();
        $failedFirst = $cycles->filter(fn (array $c) => (bool) $c['initial_failed'])->count();
        $recoveredRetry = $cycles->where('status', self::STATUS_RECOVERED_RETRY)->count();
        $unrecovered = $cycles->where('status', self::STATUS_UNRECOVERED)->count();
        $inProgress = $cycles->where('status', self::STATUS_IN_PROGRESS)->count();
        $collected = $successFirst + $recoveredRetry;

        $recoveredRevenue = $cycles->sum('recovered_retry_revenue');
        $lostRevenue = $cycles->sum('lost_amount');

        $recoveryBuckets = $this->recoveryByAttempt($cycles);
        $secondSuccess = (int) ($recoveryBuckets[1]['count'] ?? 0);
        $thirdSuccess = $this->countSubsequentRebillSuccessAfterRecovery($cycles);

        return [
            'total_due' => $due,
            'total_attempted' => $attempted,
            'successful_first_attempt' => $successFirst,
            'failed_first_attempt' => $failedFirst,
            'recovered_after_retry' => $recoveredRetry,
            'permanently_unrecovered' => $unrecovered,
            'recovery_in_progress' => $inProgress,
            'pending_recovery' => $inProgress,
            'second_attempt_success' => $secondSuccess,
            'third_attempt_success' => $thirdSuccess,
            'overall_success_rate' => $due > 0 ? round(($collected / $due) * 100, 2) : 0.0,
            'retry_recovery_rate' => $this->retryRecoveryRate($cycles),
            'recovery_success_rate' => $this->retryRecoveryRate($cycles),
            'revenue_recovered_retries' => round($recoveredRevenue, 2),
            'revenue_lost_unrecovered' => round($lostRevenue, 2),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $paymentAttemptStats
     * @return array<string, mixed>
     */
    private function recoverySequenceSummary(Collection $cycles, array $paymentAttemptStats): array
    {
        $kpis = $this->computeKpis($cycles, $paymentAttemptStats);

        return [
            'total_failed_rebills' => $kpis['failed_first_attempt'],
            'successfully_recovered' => $kpis['recovered_after_retry'],
            'second_attempt_success' => $kpis['second_attempt_success'],
            'third_attempt_success' => $kpis['third_attempt_success'],
            'recovery_success_rate' => $kpis['recovery_success_rate'],
            'pending_recovery' => $kpis['pending_recovery'],
            'total_recovered_amount' => $kpis['revenue_recovered_retries'],
            'by_payment_sequence' => $paymentAttemptStats,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $cycles
     * @return list<array<string, mixed>>
     */
    private function buildFeaturedRecoveryJourneys(Collection $cycles, int $limit = 5): array
    {
        $bySubscription = $cycles->groupBy(fn (array $c) => $c['subscription']?->id ?? 0);
        $journeys = [];

        foreach ($bySubscription as $group) {
            $sorted = $group->sortBy('cycle_step')->values();

            foreach ($sorted as $cycle) {
                if ($cycle['status'] !== self::STATUS_RECOVERED_RETRY) {
                    continue;
                }

                $nextCycle = $sorted->first(function (array $c) use ($cycle) {
                    return (int) $c['cycle_step'] > (int) $cycle['cycle_step']
                        && $c['status'] === self::STATUS_SUCCESS_FIRST;
                });

                if ($nextCycle === null) {
                    continue;
                }

                $subscription = $cycle['subscription'];
                $customer = $subscription?->customer;
                $steps = [];
                $sequence = 0;

                foreach (collect($cycle['entries'])->values() as $entry) {
                    if (! $entry instanceof BillingLedgerEntry) {
                        continue;
                    }
                    $sequence++;
                    $failed = $entry->outcome === BillingLedgerOutcome::Failure;
                    $steps[] = $this->journeyStepFromLedger($entry, $subscription, $sequence, (int) $cycle['cycle_step'], $failed ? 'failed' : 'successful');
                }

                $nextSuccess = collect($nextCycle['entries'])->first(fn ($e) => $e instanceof BillingLedgerEntry && $e->outcome === BillingLedgerOutcome::Success);
                if ($nextSuccess instanceof BillingLedgerEntry) {
                    $sequence++;
                    $steps[] = $this->journeyStepFromLedger(
                        $nextSuccess,
                        $subscription,
                        $sequence,
                        (int) $nextCycle['cycle_step'],
                        'successful',
                        'Subsequent recurring payment completed',
                    );
                }

                $journeys[] = [
                    'is_simulated' => false,
                    'subscription_id' => $subscription?->uuid,
                    'customer_reference' => trim(($customer?->first_name ?? '').' '.($customer?->last_name ?? '')) ?: ($customer?->email ?? '—'),
                    'recovery_outcome' => 'Recovered after failed first rebill; next cycle collected',
                    'timeline' => $steps,
                ];

                if (count($journeys) >= $limit) {
                    return $journeys;
                }
            }
        }

        return $journeys;
    }

    /** @param  Collection<int, array<string, mixed>>  $cycles */
    private function countSubsequentRebillSuccessAfterRecovery(Collection $cycles): int
    {
        $count = 0;
        $bySubscription = $cycles->groupBy(fn (array $c) => $c['subscription']?->id ?? 0);

        foreach ($bySubscription as $group) {
            $sorted = $group->sortBy('cycle_step')->values();

            foreach ($sorted as $cycle) {
                if ($cycle['status'] !== self::STATUS_RECOVERED_RETRY) {
                    continue;
                }

                $hasNextFirstSuccess = $sorted->contains(fn (array $c) => (int) $c['cycle_step'] > (int) $cycle['cycle_step']
                    && $c['status'] === self::STATUS_SUCCESS_FIRST);

                if ($hasNextFirstSuccess) {
                    $count++;
                }
            }
        }

        return $count;
    }

    private function journeyStepFromLedger(
        BillingLedgerEntry $entry,
        ?Subscription $subscription,
        int $rebillAttemptNumber,
        int $cycleStep,
        string $statusBadge,
        ?string $description = null,
    ): array {
        $failed = $entry->outcome === BillingLedgerOutcome::Failure;

        return [
            'rebill_attempt_number' => $rebillAttemptNumber,
            'cycle_step' => $cycleStep,
            'attempted_at' => $entry->attempted_at?->toIso8601String(),
            'amount' => (float) $entry->amount,
            'currency' => $entry->currency,
            'gateway' => $this->gatewayLabel($subscription),
            'transaction_reference' => $entry->transaction_id,
            'payment_status' => $entry->outcome->value,
            'status_badge' => $statusBadge,
            'recovery_outcome' => match (true) {
                $failed => 'Pending retry or unrecovered',
                $rebillAttemptNumber === 1 => 'First attempt collected',
                default => 'Recovery or subsequent rebill collected',
            },
            'description' => $description ?? ($failed
                ? 'Initial rebill attempt — payment unsuccessful'
                : ($rebillAttemptNumber === 2 ? 'Second attempt — recovery payment completed' : 'Rebill payment successful')),
        ];
    }

    /** @param  Collection<int, array<string, mixed>>  $cycles */
    private function retryRecoveryRate(Collection $cycles): float
    {
        $eligible = $cycles->filter(fn (array $c) => (bool) $c['initial_failed']
            && in_array($c['status'], [self::STATUS_RECOVERED_RETRY, self::STATUS_UNRECOVERED, self::STATUS_IN_PROGRESS], true));

        if ($eligible->isEmpty()) {
            return 0.0;
        }

        $recovered = $eligible->where('status', self::STATUS_RECOVERED_RETRY)->count();

        return round(($recovered / $eligible->count()) * 100, 2);
    }

    /** @param  Collection<int, array<string, mixed>>  $cycles */
    private function computeRates(Collection $cycles): array
    {
        $due = max(1, $cycles->count());
        $collected = $cycles->whereIn('status', [self::STATUS_SUCCESS_FIRST, self::STATUS_RECOVERED_RETRY])->count();
        $initialAttempts = $cycles->count();
        $firstSuccess = $cycles->where('status', self::STATUS_SUCCESS_FIRST)->count();
        $initialFailedEligible = $cycles->filter(fn (array $c) => (bool) $c['initial_failed'])->count();
        $recoveredFromFail = $cycles->where('status', self::STATUS_RECOVERED_RETRY)->count();

        return [
            'first_attempt_success_rate' => $initialAttempts > 0 ? round(($firstSuccess / $initialAttempts) * 100, 2) : 0.0,
            'retry_recovery_rate' => $initialFailedEligible > 0 ? round(($recoveredFromFail / $initialFailedEligible) * 100, 2) : 0.0,
            'overall_collection_rate' => round(($collected / $due) * 100, 2),
        ];
    }

    /** @param  Collection<int, array<string, mixed>>  $cycles */
    private function recoveryByAttempt(Collection $cycles): array
    {
        $recovered = $cycles->where('status', self::STATUS_RECOVERED_RETRY);
        $initiallyFailed = $cycles->filter(fn (array $c) => (bool) $c['initial_failed']);
        $buckets = [
            ['key' => 1, 'label' => '1st attempt (initial charge)', 'min_retry' => 0, 'max_retry' => 0, 'scope' => 'first_success'],
            ['key' => 2, 'label' => '2nd attempt (1st retry)', 'min_retry' => 1, 'max_retry' => 1, 'scope' => 'recovered'],
            ['key' => 3, 'label' => '3rd attempt (2nd retry)', 'min_retry' => 2, 'max_retry' => 2, 'scope' => 'recovered'],
            ['key' => 4, 'label' => '4th+ attempt', 'min_retry' => 3, 'max_retry' => 999, 'scope' => 'recovered'],
        ];

        $rows = [];
        foreach ($buckets as $bucket) {
            if ($bucket['scope'] === 'first_success') {
                $matches = $cycles->where('status', self::STATUS_SUCCESS_FIRST);
            } else {
                $matches = $recovered->filter(function (array $c) use ($bucket) {
                    $idx = (int) ($c['success_entry']?->retry_attempt ?? -1);

                    return $idx >= $bucket['min_retry'] && $idx <= $bucket['max_retry'];
                });
            }

            $count = $matches->count();
            $revenue = $matches->sum('collected_amount');
            $pctInitialFail = $initiallyFailed->count() > 0 && $bucket['scope'] === 'recovered'
                ? round(($count / $initiallyFailed->count()) * 100, 2)
                : ($cycles->count() > 0 && $bucket['scope'] === 'first_success' ? round(($count / $cycles->count()) * 100, 2) : 0.0);

            $rows[] = [
                'label' => $bucket['label'],
                'count' => $count,
                'recovered_revenue' => round((float) $revenue, 2),
                'percent' => $pctInitialFail,
            ];
        }

        return $rows;
    }

    /** @param  Collection<int, array<string, mixed>>  $cycles */
    private function buildCharts(Collection $cycles, DashboardFilter $filter): array
    {
        $success = $cycles->whereIn('status', [self::STATUS_SUCCESS_FIRST, self::STATUS_RECOVERED_RETRY])->count();
        $failed = $cycles->where('status', self::STATUS_UNRECOVERED)->count();
        $inProgress = $cycles->where('status', self::STATUS_IN_PROGRESS)->count();

        $trend = $this->buildDailyTrend($cycles, $filter);

        $gateway = $cycles
            ->groupBy(fn (array $c) => $c['subscription']?->payment_provider ?? 'unknown')
            ->map(function (Collection $group, string $provider) {
                $collected = $group->whereIn('status', [self::STATUS_SUCCESS_FIRST, self::STATUS_RECOVERED_RETRY])->count();

                return [
                    'label' => strtoupper($provider),
                    'attempts' => $group->count(),
                    'collected' => $collected,
                    'rate' => $group->count() > 0 ? round(($collected / $group->count()) * 100, 1) : 0,
                ];
            })
            ->values()
            ->all();

        $campaign = $cycles
            ->groupBy(fn (array $c) => (string) ($c['subscription']?->platform_campaign_id ?? 'none'))
            ->map(function (Collection $group, string $campaignId) {
                $name = $group->first()['subscription']?->platformCampaign?->name ?? 'Unassigned';
                $collected = $group->whereIn('status', [self::STATUS_SUCCESS_FIRST, self::STATUS_RECOVERED_RETRY])->count();

                return [
                    'label' => $name,
                    'attempts' => $group->count(),
                    'collected' => $collected,
                    'rate' => $group->count() > 0 ? round(($collected / $group->count()) * 100, 1) : 0,
                ];
            })
            ->sortByDesc('attempts')
            ->values()
            ->take(12)
            ->all();

        $declines = collect();
        foreach ($cycles as $cycle) {
            $firstFail = collect($cycle['entries'])->first(fn (BillingLedgerEntry $e) => $e->outcome === BillingLedgerOutcome::Failure);
            if ($firstFail) {
                $code = $firstFail->failure_code ?: 'unknown';
                $declines[$code] = ($declines[$code] ?? 0) + 1;
            }
        }

        return [
            'outcome' => [
                ['label' => 'Collected', 'value' => $success],
                ['label' => 'Unrecovered', 'value' => $failed],
                ['label' => 'Retry in progress', 'value' => $inProgress],
            ],
            'recovery_by_attempt' => $this->recoveryByAttempt($cycles),
            'trend' => $trend,
            'gateway' => $gateway,
            'campaign' => $campaign,
            'decline_reasons' => $declines->map(fn (int $count, string $code) => ['label' => $code, 'value' => $count])->values()->all(),
        ];
    }

    /** @param  Collection<int, array<string, mixed>>  $cycles */
    private function buildDailyTrend(Collection $cycles, DashboardFilter $filter): array
    {
        $cursor = $filter->from->copy()->startOfDay();
        $end = $filter->to->copy()->endOfDay();
        $buckets = [];

        while ($cursor <= $end) {
            $day = $cursor->toDateString();
            $buckets[$day] = ['label' => $cursor->format('M j'), 'collected' => 0, 'recovered_revenue' => 0.0];
            $cursor->addDay();
        }

        foreach ($cycles as $cycle) {
            /** @var CarbonInterface|null $at */
            $at = $cycle['first_attempt_at'];
            if ($at === null) {
                continue;
            }
            $day = $at->format('Y-m-d');
            if (! isset($buckets[$day])) {
                continue;
            }
            if (in_array($cycle['status'], [self::STATUS_SUCCESS_FIRST, self::STATUS_RECOVERED_RETRY], true)) {
                $buckets[$day]['collected']++;
            }
            $buckets[$day]['recovered_revenue'] += (float) $cycle['recovered_retry_revenue'];
        }

        return array_values($buckets);
    }

    /** @param  Collection<int, array<string, mixed>>  $cycles */
    private function buildReportRows(Collection $cycles): array
    {
        return $cycles->map(function (array $cycle) {
            $subscription = $cycle['subscription'];
            $customer = $subscription?->customer;
            $first = $cycle['first_entry'];

            $attempts = collect($cycle['entries'])
                ->values()
                ->map(function (BillingLedgerEntry $e, int $index) use ($subscription) {
                    return [
                        'attempted_at' => $e->attempted_at?->toIso8601String(),
                        'charge_sequence' => $index + 1,
                        'ledger_retry_attempt' => (int) $e->retry_attempt,
                        'attempt_number' => $index + 1,
                        'outcome' => $e->outcome->value,
                        'gateway' => $this->gatewayLabel($subscription),
                        'transaction_reference' => $e->transaction_id,
                        'failure_code' => $e->failure_code,
                        'failure_message' => $e->failure_message,
                        'amount' => (float) $e->amount,
                    ];
                })
                ->all();

            $cycleBlock = $this->cycleHistory->formatCycleBlock($cycle);

            return [
                'customer_name' => trim(($customer?->first_name ?? '').' '.($customer?->last_name ?? '')) ?: ($customer?->email ?? '—'),
                'customer_id' => $customer?->uuid,
                'subscription_id' => $subscription?->uuid,
                'order_id' => $subscription?->order?->order_number,
                'campaign' => $subscription?->platformCampaign?->name,
                'cycle_step' => $cycle['cycle_step'],
                'rebill_cycle' => $cycleBlock,
                'due_at' => $cycleBlock['billing_due_at'] ?? $subscription?->next_billing_at?->toIso8601String(),
                'original_amount' => $cycle['scheduled_amount'],
                'currency' => $cycle['currency'],
                'initial_attempt_at' => $first?->attempted_at?->toIso8601String(),
                'initial_result' => $first?->outcome->value,
                'retry_count' => count($attempts) - 1,
                'attempts' => $attempts,
                'retry_attempts' => $cycleBlock['retry_attempts'],
                'final_status' => $this->statusLabel($cycle['status']),
                'final_status_key' => $cycle['status'],
                'cycle_status_label' => $cycleBlock['cycle_status_label'],
                'cycle_status' => $cycleBlock['cycle_status'],
                'recovery_attempt_number' => $cycle['recovery_attempt_number'],
                'total_collected' => $cycle['collected_amount'],
                'recovered_revenue' => $cycle['recovered_retry_revenue'],
            ];
        })->sortByDesc('initial_attempt_at')->values()->all();
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_SUCCESS_FIRST => 'Successful First Attempt',
            self::STATUS_RECOVERED_RETRY => 'Recovered on Retry',
            self::STATUS_IN_PROGRESS => 'Retry In Progress',
            default => 'Unrecovered',
        };
    }

    /** @param  Collection<int, array<string, mixed>>  $cycles */
    private function applyKpiFilter(Collection $cycles, ?string $filter): Collection
    {
        if (blank($filter)) {
            return $cycles;
        }

        return $cycles->filter(fn (array $c) => match ($filter) {
            'due', 'attempted' => true,
            'success_first' => $c['status'] === self::STATUS_SUCCESS_FIRST,
            'failed_first' => (bool) $c['initial_failed'],
            'recovered' => $c['status'] === self::STATUS_RECOVERED_RETRY,
            'unrecovered' => $c['status'] === self::STATUS_UNRECOVERED,
            'in_progress' => $c['status'] === self::STATUS_IN_PROGRESS,
            default => true,
        })->values();
    }

    /** @return Builder<Subscription> */
    private function subscriptionFilteredSubquery(DashboardFilter $filter): Builder
    {
        return Subscription::query()
            ->when($filter->currency, fn (Builder $q) => $q->where('currency', $filter->currency))
            ->when($filter->campaignId, fn (Builder $q) => $q->where('platform_campaign_id', $filter->campaignId))
            ->when($filter->campaignCategory, fn (Builder $q) => $q->whereHas(
                'platformCampaign',
                fn (Builder $c) => $c->where('metadata->category', $filter->campaignCategory),
            ))
            ->when($filter->paymentGatewayId, fn (Builder $q) => $q->where('payment_gateway_id', $filter->paymentGatewayId))
            ->select('id');
    }

    /** @return list<string> */
    public function limitations(): array
    {
        return [
            'Metrics are derived from billing_ledger_entries only (subscription rebill attempts). Initial checkout failures are excluded.',
            'Rebill cycles are grouped by subscription_id + cycle_step. There is no separate billing_cycle_uuid column.',
            'Cycles are included when the first ledger attempt in that cycle falls within the selected date range.',
            'Attempts on the same cycle that occurred before the reporting window are included once the cycle is in scope (full attempt trail for that cycle).',
            'retry_attempt on success rows reflects subscription.retry_count before charge; failure rows use retry_count + 1.',
            'Retry schedules and past next_billing_at values before ledger adoption may be incomplete; do not infer missing historical attempts.',
            'Open retry windows (past_due, retries remaining) are not counted as permanently failed.',
            'Payment gateway on attempt rows reflects the subscription payment_provider (ledger rows do not store per-attempt gateway).',
            'Second attempt success counts cycles recovered on the 2nd charge (1st retry). Third attempt success counts the next billing cycle collected after a recovered cycle (not a 3rd in-cycle retry).',
            'When REBILL_RECOVERY_SHOWCASE=true (local only by default), a SIMULATED journey is shown and is never merged into KPIs or exports.',
            'Authoritative rebill cycle identifier: billing_ledger_entries.cycle_step (with subscription_id). Retry identifier: ledger row idempotency_key and ordered rows within the same subscription_id + cycle_step group.',
            'Retry attempts never increment cycle_step; a new recurring rebill cycle is a distinct cycle_step value.',
            'Per-cycle historical billing due dates are not persisted on ledger rows; due date is shown only when subscription.current_cycle_step matches the cycle and next_billing_at is set.',
        ];
    }

    /**
     * Dataset for multi-sheet Excel export. KPIs and rollups always use the full filtered cycle population;
     * detailed cycle and attempt rows respect $reportFilter when provided (same as dashboard report CSV).
     *
     * @return array<string, mixed>
     */
    public function exportPayload(DashboardFilter $filter, ?string $reportFilter = null): array
    {
        $cycles = $this->loadCycles($filter);
        $detailCycles = $this->applyKpiFilter($cycles, $reportFilter);

        return [
            'period' => [
                'from' => $filter->from->toIso8601String(),
                'to' => $filter->to->toIso8601String(),
                'currency' => $filter->currency,
                'quick_range' => $filter->quickRange,
            ],
            'generated_at' => Carbon::now()->toIso8601String(),
            'filters' => $this->describeFilters($filter, $reportFilter),
            'limitations' => $this->limitations(),
            'kpis' => $this->computeKpis($cycles, $this->paymentAttemptStatistics($cycles)),
            'rates' => $this->computeRates($cycles),
            'amounts' => $this->computeAmountSummaries($cycles),
            'recovery_by_attempt_cycles' => $this->recoveryByAttempt($cycles),
            'recovery_by_payment_attempt' => $this->paymentAttemptStatistics($cycles),
            'campaign_performance' => $this->performanceByCampaign($cycles),
            'gateway_performance' => $this->performanceByGateway($cycles),
            'failure_reasons' => $this->failureReasonStatistics($cycles),
            'cycle_rows' => $this->buildExportCycleRows($detailCycles),
            'attempt_rows' => $this->buildAttemptHistoryRows($detailCycles),
            'in_progress_rows' => $this->buildInProgressRows($detailCycles),
            'unrecovered_rows' => $this->buildUnrecoveredRows($detailCycles),
        ];
    }

    /** @param  Collection<int, array<string, mixed>>  $cycles */
    private function computeAmountSummaries(Collection $cycles): array
    {
        $initialFailedAmount = $cycles
            ->filter(fn (array $c) => (bool) $c['initial_failed'])
            ->sum(fn (array $c) => (float) $c['scheduled_amount']);

        return [
            'initially_failed_amount' => round($initialFailedAmount, 2),
            'revenue_recovered_retries' => round((float) $cycles->sum('recovered_retry_revenue'), 2),
            'revenue_still_unrecovered' => round((float) $cycles->sum('lost_amount'), 2),
        ];
    }

    /** @param  Collection<int, array<string, mixed>>  $cycles */
    private function paymentAttemptStatistics(Collection $cycles): array
    {
        $buckets = [
            1 => 'Initial charge',
            2 => 'First retry',
            3 => 'Second retry',
            4 => 'Third retry',
            5 => 'Later retries (5+)',
        ];

        /** @var array<int, array{total: int, successful: int, failed: int, revenue: float}> $stats */
        $stats = [];
        foreach (array_keys($buckets) as $key) {
            $stats[$key] = ['total' => 0, 'successful' => 0, 'failed' => 0, 'revenue' => 0.0];
        }

        foreach ($cycles as $cycle) {
            /** @var Collection<int, BillingLedgerEntry> $entries */
            $entries = collect($cycle['entries']);
            foreach ($entries as $index => $entry) {
                $sequence = $index + 1;
                $bucket = match (true) {
                    $sequence === 1 => 1,
                    $sequence === 2 => 2,
                    $sequence === 3 => 3,
                    $sequence === 4 => 4,
                    default => 5,
                };
                $stats[$bucket]['total']++;
                if ($entry->outcome === BillingLedgerOutcome::Success) {
                    $stats[$bucket]['successful']++;
                    $stats[$bucket]['revenue'] += (float) $entry->amount;
                } else {
                    $stats[$bucket]['failed']++;
                }
            }
        }

        $rows = [];
        foreach ($buckets as $attempt => $description) {
            $row = $stats[$attempt];
            $total = $row['total'];
            $successRate = $total > 0 ? round(($row['successful'] / $total) * 100, 2) : 0.0;

            $rows[] = [
                'attempt' => $attempt,
                'description' => $description,
                'total_attempts' => $total,
                'successful' => $row['successful'],
                'failed' => $row['failed'],
                'success_rate' => $successRate,
                'revenue_collected' => round($row['revenue'], 2),
            ];
        }

        return $rows;
    }

    /** @param  Collection<int, array<string, mixed>>  $cycles */
    private function performanceByCampaign(Collection $cycles): array
    {
        return $cycles
            ->groupBy(fn (array $c) => (string) ($c['subscription']?->platform_campaign_id ?? '0'))
            ->map(function (Collection $group) {
                $name = $group->first()['subscription']?->platformCampaign?->name ?? 'Unassigned';

                return array_merge(['label' => $name], $this->performanceMetricsForGroup($group));
            })
            ->sortByDesc('total_cycles')
            ->values()
            ->all();
    }

    /** @param  Collection<int, array<string, mixed>>  $cycles */
    private function performanceByGateway(Collection $cycles): array
    {
        return $cycles
            ->groupBy(fn (array $c) => strtoupper((string) ($c['subscription']?->payment_provider ?? 'unknown')))
            ->map(function (Collection $group, string $provider) {
                $gatewayConfig = $group->first()['subscription']?->paymentGateway;
                $label = $gatewayConfig?->name
                    ? $gatewayConfig->name.' ('.$provider.')'
                    : $provider;

                return array_merge([
                    'label' => $label,
                    'provider' => $provider,
                    'attribution_note' => 'Subscription-level gateway; ledger attempts do not store per-attempt gateway.',
                ], $this->performanceMetricsForGroup($group));
            })
            ->sortByDesc('total_cycles')
            ->values()
            ->all();
    }

    /** @param  Collection<int, array<string, mixed>>  $group */
    private function performanceMetricsForGroup(Collection $group): array
    {
        $rates = $this->computeRates($group);
        $collected = $group->whereIn('status', [self::STATUS_SUCCESS_FIRST, self::STATUS_RECOVERED_RETRY])->count();

        return [
            'total_cycles' => $group->count(),
            'success_first' => $group->where('status', self::STATUS_SUCCESS_FIRST)->count(),
            'failed_first' => $group->filter(fn (array $c) => (bool) $c['initial_failed'])->count(),
            'recovered' => $group->where('status', self::STATUS_RECOVERED_RETRY)->count(),
            'in_progress' => $group->where('status', self::STATUS_IN_PROGRESS)->count(),
            'unrecovered' => $group->where('status', self::STATUS_UNRECOVERED)->count(),
            'retry_recovery_rate' => $this->retryRecoveryRate($group),
            'collection_rate' => $rates['overall_collection_rate'],
            'collected_cycles' => $collected,
            'recovered_revenue' => round((float) $group->sum('recovered_retry_revenue'), 2),
            'unrecovered_revenue' => round((float) $group->sum('lost_amount'), 2),
        ];
    }

    /** @param  Collection<int, array<string, mixed>>  $cycles */
    private function failureReasonStatistics(Collection $cycles): array
    {
        /** @var array<string, array{failure_code: string, failure_message: ?string, gateway: string, occurrences: int, cycle_statuses: array<string, string>}> $groups */
        $groups = [];

        foreach ($cycles as $cycle) {
            $subscription = $cycle['subscription'];
            $gateway = strtoupper((string) ($subscription?->payment_provider ?? 'unknown'));
            $cycleKey = (string) $cycle['cycle_key'];
            $cycleStatus = (string) $cycle['status'];

            foreach ($cycle['entries'] as $entry) {
                if (! $entry instanceof BillingLedgerEntry || $entry->outcome !== BillingLedgerOutcome::Failure) {
                    continue;
                }

                $code = $entry->failure_code ?: 'unknown';
                $message = $entry->failure_message;
                $groupKey = $code."\0".($message ?? '')."\0".$gateway;

                if (! isset($groups[$groupKey])) {
                    $groups[$groupKey] = [
                        'failure_code' => $code,
                        'failure_message' => $message,
                        'gateway' => $gateway,
                        'occurrences' => 0,
                        'cycle_statuses' => [],
                    ];
                }

                $groups[$groupKey]['occurrences']++;
                $groups[$groupKey]['cycle_statuses'][$cycleKey] = $cycleStatus;
            }
        }

        $rows = [];
        foreach ($groups as $group) {
            $affected = count($group['cycle_statuses']);
            $recovered = count(array_filter(
                $group['cycle_statuses'],
                fn (string $status) => $status === self::STATUS_RECOVERED_RETRY,
            ));
            $recoveryPct = $affected > 0 ? round(($recovered / $affected) * 100, 2) : 0.0;

            $rows[] = [
                'failure_code' => $group['failure_code'],
                'failure_message' => $group['failure_message'],
                'gateway' => $group['gateway'],
                'occurrences' => $group['occurrences'],
                'affected_cycles' => $affected,
                'subsequent_recovery_count' => $recovered,
                'recovery_percentage' => $recoveryPct,
            ];
        }

        usort($rows, fn (array $a, array $b) => $b['occurrences'] <=> $a['occurrences']);

        return $rows;
    }

    /** @param  Collection<int, array<string, mixed>>  $cycles */
    private function buildExportCycleRows(Collection $cycles): array
    {
        return $cycles->map(function (array $cycle) {
            $subscription = $cycle['subscription'];
            $customer = $subscription?->customer;
            $first = $cycle['first_entry'];
            $entries = collect($cycle['entries']);
            $attemptCount = $entries->count();
            $lastFailure = $entries->last(fn (BillingLedgerEntry $e) => $e->outcome === BillingLedgerOutcome::Failure);

            return [
                'customer_name' => trim(($customer?->first_name ?? '').' '.($customer?->last_name ?? '')) ?: ($customer?->email ?? '—'),
                'customer_id' => $customer?->uuid,
                'customer_email' => $customer?->email,
                'subscription_id' => $subscription?->uuid,
                'order_id' => $subscription?->order?->order_number,
                'campaign' => $subscription?->platformCampaign?->name,
                'plan_name' => $subscription?->plan?->name,
                'cycle_step' => $cycle['cycle_step'],
                'scheduled_rebill_at' => $subscription?->next_billing_at?->toIso8601String(),
                'original_amount' => $cycle['scheduled_amount'],
                'currency' => $cycle['currency'],
                'initial_result' => $first?->outcome->value,
                'total_attempts' => $attemptCount,
                'final_status' => $this->statusLabel($cycle['status']),
                'final_status_key' => $cycle['status'],
                'recovery_attempt_number' => $cycle['recovery_attempt_number'],
                'total_collected' => $cycle['collected_amount'],
                'recovered_revenue' => $cycle['recovered_retry_revenue'],
                'unrecovered_amount' => $cycle['lost_amount'],
                'retry_status' => $this->retryStatusLabel($subscription, $cycle['status']),
                'payment_gateway' => $this->gatewayLabel($subscription),
                'initial_attempt_at' => $first?->attempted_at?->toIso8601String(),
            ];
        })->sortByDesc('initial_attempt_at')->values()->all();
    }

    /** @param  Collection<int, array<string, mixed>>  $cycles */
    private function buildAttemptHistoryRows(Collection $cycles): array
    {
        $rows = [];

        foreach ($cycles as $cycle) {
            $subscription = $cycle['subscription'];
            $customer = $subscription?->customer;
            $customerName = trim(($customer?->first_name ?? '').' '.($customer?->last_name ?? '')) ?: ($customer?->email ?? '—');

            foreach (collect($cycle['entries'])->values() as $index => $entry) {
                if (! $entry instanceof BillingLedgerEntry) {
                    continue;
                }

                $rows[] = [
                    'customer_name' => $customerName,
                    'customer_id' => $customer?->uuid,
                    'subscription_id' => $subscription?->uuid,
                    'order_id' => $subscription?->order?->order_number,
                    'campaign' => $subscription?->platformCampaign?->name,
                    'cycle_step' => $cycle['cycle_step'],
                    'attempt_sequence' => $index + 1,
                    'ledger_retry_attempt' => (int) $entry->retry_attempt,
                    'attempted_at' => $entry->attempted_at?->toIso8601String(),
                    'amount' => (float) $entry->amount,
                    'currency' => $entry->currency,
                    'outcome' => $entry->outcome->value,
                    'gateway' => $this->gatewayLabel($subscription),
                    'failure_code' => $entry->failure_code,
                    'failure_message' => $entry->failure_message,
                    'idempotency_key' => $entry->idempotency_key,
                ];
            }
        }

        usort($rows, fn (array $a, array $b) => strcmp((string) ($b['attempted_at'] ?? ''), (string) ($a['attempted_at'] ?? '')));

        return $rows;
    }

    /** @param  Collection<int, array<string, mixed>>  $cycles */
    private function buildInProgressRows(Collection $cycles): array
    {
        return $cycles
            ->where('status', self::STATUS_IN_PROGRESS)
            ->map(function (array $cycle) {
                $subscription = $cycle['subscription'];
                $customer = $subscription?->customer;
                $first = $cycle['first_entry'];
                $entries = collect($cycle['entries']);
                $lastFailure = $entries->last(fn (BillingLedgerEntry $e) => $e->outcome === BillingLedgerOutcome::Failure);

                return [
                    'customer_name' => trim(($customer?->first_name ?? '').' '.($customer?->last_name ?? '')) ?: ($customer?->email ?? '—'),
                    'customer_id' => $customer?->uuid,
                    'subscription_id' => $subscription?->uuid,
                    'campaign' => $subscription?->platformCampaign?->name,
                    'initial_failure_at' => $first?->attempted_at?->toIso8601String(),
                    'scheduled_amount' => $cycle['scheduled_amount'],
                    'currency' => $cycle['currency'],
                    'attempts_made' => $entries->count(),
                    'last_failure_code' => $lastFailure?->failure_code,
                    'last_failure_message' => $lastFailure?->failure_message,
                    'next_scheduled_retry' => $subscription?->next_billing_at?->toIso8601String()
                        ?? $subscription?->next_dunning_at?->toIso8601String(),
                    'retry_status' => $this->retryStatusLabel($subscription, $cycle['status']),
                ];
            })
            ->values()
            ->all();
    }

    /** @param  Collection<int, array<string, mixed>>  $cycles */
    private function buildUnrecoveredRows(Collection $cycles): array
    {
        return $cycles
            ->where('status', self::STATUS_UNRECOVERED)
            ->map(function (array $cycle) {
                $subscription = $cycle['subscription'];
                $customer = $subscription?->customer;
                $first = $cycle['first_entry'];
                $entries = collect($cycle['entries']);
                $lastFailure = $entries->last(fn (BillingLedgerEntry $e) => $e->outcome === BillingLedgerOutcome::Failure);

                return [
                    'customer_name' => trim(($customer?->first_name ?? '').' '.($customer?->last_name ?? '')) ?: ($customer?->email ?? '—'),
                    'customer_id' => $customer?->uuid,
                    'subscription_id' => $subscription?->uuid,
                    'campaign' => $subscription?->platformCampaign?->name,
                    'initial_failure_at' => $first?->attempted_at?->toIso8601String(),
                    'total_attempts' => $entries->count(),
                    'last_failure_code' => $lastFailure?->failure_code,
                    'last_failure_message' => $lastFailure?->failure_message,
                    'scheduled_amount' => $cycle['scheduled_amount'],
                    'unrecovered_amount' => $cycle['lost_amount'],
                    'currency' => $cycle['currency'],
                    'final_status' => $this->statusLabel($cycle['status']),
                ];
            })
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function describeFilters(DashboardFilter $filter, ?string $reportFilter): array
    {
        $campaign = 'All campaigns';
        if ($filter->campaignId) {
            $campaign = Campaign::query()->find($filter->campaignId)?->name ?? 'Campaign #'.$filter->campaignId;
        }

        $gateway = 'All payment gateways';
        if ($filter->paymentGatewayId) {
            $gateway = PaymentGatewayConfig::query()->find($filter->paymentGatewayId)?->name
                ?? 'Gateway #'.$filter->paymentGatewayId;
        }

        $status = match ($reportFilter) {
            'success_first' => 'Successful on first attempt',
            'failed_first' => 'Initially failed',
            'recovered' => 'Recovered on retry',
            'unrecovered' => 'Permanently unrecovered',
            'in_progress' => 'Recovery in progress',
            'due', 'attempted' => 'All rebill cycles (due)',
            default => 'All rebill statuses',
        };

        return [
            'campaign' => $campaign,
            'gateway' => $gateway,
            'currency' => $filter->currency ?? 'All currencies',
            'campaign_category' => $filter->campaignCategory ?: 'All categories',
            'rebill_status_filter' => $status,
            'rebill_status_key' => $reportFilter,
        ];
    }

    private function gatewayLabel(?Subscription $subscription): string
    {
        if ($subscription === null) {
            return '—';
        }

        $provider = strtoupper((string) ($subscription->payment_provider ?? 'unknown'));

        return $subscription->paymentGateway?->name
            ? $subscription->paymentGateway->name.' ('.$provider.')'
            : $provider;
    }

    private function retryStatusLabel(?Subscription $subscription, string $cycleStatus): string
    {
        if ($cycleStatus === self::STATUS_SUCCESS_FIRST) {
            return 'Collected on first attempt';
        }

        if ($cycleStatus === self::STATUS_RECOVERED_RETRY) {
            return 'Recovered on retry';
        }

        if ($cycleStatus === self::STATUS_UNRECOVERED) {
            return 'Permanently unrecovered (closed)';
        }

        if ($subscription === null) {
            return 'Recovery in progress';
        }

        $max = (int) ($subscription->plan?->retryPolicy?->max_attempts ?? 0);
        $retry = (int) $subscription->retry_count;

        return sprintf(
            'Past due — retry %d%s, status %s',
            $retry,
            $max > 0 ? '/'.$max : '',
            $subscription->status->value ?? (string) $subscription->status,
        );
    }
}
