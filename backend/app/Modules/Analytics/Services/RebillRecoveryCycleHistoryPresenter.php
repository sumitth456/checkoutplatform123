<?php

namespace App\Modules\Analytics\Services;

use App\Models\BillingLedgerEntry;
use App\Models\Subscription;
use App\Modules\Subscription\Enums\BillingLedgerOutcome;
use Illuminate\Support\Collection;

/**
 * Maps billing_ledger_entries (subscription_id + cycle_step + retry_attempt) into
 * cycle-level and retry-level presentation. Never infers cycle_step from attempt order.
 */
final class RebillRecoveryCycleHistoryPresenter
{
    public const CYCLE_STATUS_SUCCESSFUL = 'successful';

    public const CYCLE_STATUS_FAILED = 'failed';

    public const CYCLE_STATUS_PENDING = 'pending';

    public const CYCLE_STATUS_PROCESSING = 'processing';

    public const CYCLE_STATUS_NOT_YET_DUE = 'not_yet_due';

    /**
     * @param  Collection<int, array<string, mixed>>  $cycles
     * @return array<string, mixed>
     */
    public function cycleAnalytics(Collection $cycles): array
    {
        $successful = $cycles->whereIn('status', [
            RebillRecoveryAnalyticsService::STATUS_SUCCESS_FIRST,
            RebillRecoveryAnalyticsService::STATUS_RECOVERED_RETRY,
        ]);
        $failed = $cycles->where('status', RebillRecoveryAnalyticsService::STATUS_UNRECOVERED);
        $pending = $cycles->where('status', RebillRecoveryAnalyticsService::STATUS_IN_PROGRESS);

        $byStep = [];
        foreach ([1, 2, 3] as $step) {
            $group = $cycles->where('cycle_step', $step);
            $byStep["cycle_{$step}"] = $this->outcomeSlice($group);
        }

        $fourPlus = $cycles->filter(fn (array $c) => (int) $c['cycle_step'] >= 4);
        $byStep['cycle_4_plus'] = $this->outcomeSlice($fourPlus);

        return [
            'total_cycles' => $cycles->count(),
            'successful_cycles' => $successful->count(),
            'failed_cycles' => $failed->count(),
            'pending_cycles' => $pending->count(),
            'by_rebill_cycle' => $byStep,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $cycles
     * @return array<string, mixed>
     */
    public function attemptAnalytics(Collection $cycles): array
    {
        $successful = 0;
        $failed = 0;

        foreach ($cycles as $cycle) {
            foreach ($cycle['entries'] as $entry) {
                if (! $entry instanceof BillingLedgerEntry) {
                    continue;
                }

                if ($entry->outcome === BillingLedgerOutcome::Success) {
                    $successful++;
                } else {
                    $failed++;
                }
            }
        }

        $total = $successful + $failed;

        $eligibleCycles = $cycles->filter(fn (array $c) => (bool) $c['initial_failed']);
        $recovered = $eligibleCycles->where('status', RebillRecoveryAnalyticsService::STATUS_RECOVERED_RETRY)->count();
        $recoveryRate = $eligibleCycles->count() > 0
            ? round(($recovered / $eligibleCycles->count()) * 100, 2)
            : 0.0;

        return [
            'total_retry_attempts' => $total,
            'successful_retry_attempts' => $successful,
            'failed_retry_attempts' => $failed,
            'attempt_success_rate' => $total > 0 ? round(($successful / $total) * 100, 2) : 0.0,
            'recovery_rate_after_retries' => $recoveryRate,
            'by_sequence' => $this->sequenceStats($cycles),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $cycles
     * @return list<array<string, mixed>>
     */
    public function subscriptionHistories(Collection $cycles, int $limit = 100): array
    {
        return $cycles
            ->groupBy(fn (array $c) => $c['subscription']?->id ?? 0)
            ->map(function (Collection $group) {
                /** @var Subscription|null $subscription */
                $subscription = $group->first()['subscription'] ?? null;
                $customer = $subscription?->customer;
                $ordered = $group->sortBy('cycle_step')->values();

                return [
                    'subscription_id' => $subscription?->uuid,
                    'customer_name' => trim(($customer?->first_name ?? '').' '.($customer?->last_name ?? '')) ?: ($customer?->email ?? '—'),
                    'customer_id' => $customer?->uuid,
                    'campaign' => $subscription?->platformCampaign?->name,
                    'rebill_cycles' => $ordered
                        ->map(fn (array $cycle) => $this->formatCycleBlock($cycle))
                        ->values()
                        ->all(),
                ];
            })
            ->sortByDesc(fn (array $row) => collect($row['rebill_cycles'])->max('first_attempt_at') ?? '')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $cycle
     * @return array<string, mixed>
     */
    public function formatCycleBlock(array $cycle): array
    {
        $subscription = $cycle['subscription'];
        $display = $this->cycleDisplayStatus($cycle);
        $successEntry = $cycle['success_entry'] ?? null;
        $first = $cycle['first_entry'] ?? null;

        return [
            'cycle_number' => (int) $cycle['cycle_step'],
            'cycle_identifier' => $cycle['cycle_key'] ?? null,
            'billing_due_at' => $this->cycleBillingDueAt($cycle, $subscription),
            'billing_due_note' => $this->billingDueNote($cycle, $subscription),
            'charge_at' => $successEntry instanceof BillingLedgerEntry
                ? $successEntry->attempted_at?->toIso8601String()
                : null,
            'amount' => (float) $cycle['scheduled_amount'],
            'collected_amount' => (float) $cycle['collected_amount'],
            'currency' => $cycle['currency'],
            'cycle_status' => $display['key'],
            'cycle_status_label' => $display['label'],
            'transaction_reference' => $successEntry instanceof BillingLedgerEntry
                ? $successEntry->transaction_id
                : null,
            'gateway' => $this->gatewayLabel($subscription),
            'gateway_response' => $this->gatewayResponseSummary($cycle),
            'first_attempt_at' => $first instanceof BillingLedgerEntry
                ? $first->attempted_at?->toIso8601String()
                : null,
            'retry_attempts' => $this->formatRetryAttempts($cycle),
            'internal_status_key' => $cycle['status'],
        ];
    }

    /**
     * @param  array<string, mixed>  $cycle
     * @return list<array<string, mixed>>
     */
    public function formatRetryAttempts(array $cycle): array
    {
        $subscription = $cycle['subscription'];

        return collect($cycle['entries'])
            ->values()
            ->map(function (BillingLedgerEntry $entry, int $index) use ($subscription) {
                $failed = $entry->outcome === BillingLedgerOutcome::Failure;

                return [
                    'retry_attempt_number' => $index + 1,
                    'ledger_retry_attempt' => (int) $entry->retry_attempt,
                    'attempted_at' => $entry->attempted_at?->toIso8601String(),
                    'amount' => (float) $entry->amount,
                    'currency' => $entry->currency,
                    'attempt_status' => $failed ? 'failed' : 'successful',
                    'attempt_status_label' => $failed ? 'Failed' : 'Successful',
                    'transaction_reference' => $entry->transaction_id,
                    'failure_code' => $entry->failure_code,
                    'failure_message' => $entry->failure_message,
                    'gateway' => $this->gatewayLabel($subscription),
                    'idempotency_key' => $entry->idempotency_key,
                ];
            })
            ->all();
    }

    /**
     * @param  array<string, mixed>  $cycle
     * @return array{key: string, label: string}
     */
    public function cycleDisplayStatus(array $cycle): array
    {
        return match ($cycle['status']) {
            RebillRecoveryAnalyticsService::STATUS_SUCCESS_FIRST,
            RebillRecoveryAnalyticsService::STATUS_RECOVERED_RETRY => [
                'key' => self::CYCLE_STATUS_SUCCESSFUL,
                'label' => 'Successful',
            ],
            RebillRecoveryAnalyticsService::STATUS_IN_PROGRESS => [
                'key' => self::CYCLE_STATUS_PENDING,
                'label' => 'Pending',
            ],
            RebillRecoveryAnalyticsService::STATUS_UNRECOVERED => [
                'key' => self::CYCLE_STATUS_FAILED,
                'label' => 'Failed',
            ],
            default => [
                'key' => self::CYCLE_STATUS_NOT_YET_DUE,
                'label' => 'Not Yet Due',
            ],
        };
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $group
     * @return array{total: int, successful: int, failed: int, pending: int}
     */
    private function outcomeSlice(Collection $group): array
    {
        return [
            'total' => $group->count(),
            'successful' => $group->whereIn('status', [
                RebillRecoveryAnalyticsService::STATUS_SUCCESS_FIRST,
                RebillRecoveryAnalyticsService::STATUS_RECOVERED_RETRY,
            ])->count(),
            'failed' => $group->where('status', RebillRecoveryAnalyticsService::STATUS_UNRECOVERED)->count(),
            'pending' => $group->where('status', RebillRecoveryAnalyticsService::STATUS_IN_PROGRESS)->count(),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $cycles
     * @return list<array<string, mixed>>
     */
    private function sequenceStats(Collection $cycles): array
    {
        /** @var array<int, array{total: int, successful: int, failed: int}> $buckets */
        $buckets = [];

        foreach ($cycles as $cycle) {
            foreach (collect($cycle['entries'])->values() as $index => $entry) {
                if (! $entry instanceof BillingLedgerEntry) {
                    continue;
                }

                $sequence = $index + 1;
                $buckets[$sequence]['total'] = ($buckets[$sequence]['total'] ?? 0) + 1;

                if ($entry->outcome === BillingLedgerOutcome::Success) {
                    $buckets[$sequence]['successful'] = ($buckets[$sequence]['successful'] ?? 0) + 1;
                } else {
                    $buckets[$sequence]['failed'] = ($buckets[$sequence]['failed'] ?? 0) + 1;
                }
            }
        }

        ksort($buckets);

        $rows = [];
        foreach ($buckets as $sequence => $counts) {
            $total = (int) $counts['total'];
            $rows[] = [
                'retry_attempt_number' => $sequence,
                'total' => $total,
                'successful' => (int) ($counts['successful'] ?? 0),
                'failed' => (int) ($counts['failed'] ?? 0),
                'success_rate' => $total > 0
                    ? round(((int) ($counts['successful'] ?? 0) / $total) * 100, 2)
                    : 0.0,
            ];
        }

        return $rows;
    }

    private function cycleBillingDueAt(array $cycle, ?Subscription $subscription): ?string
    {
        $cycleStep = (int) $cycle['cycle_step'];

        if ($subscription === null) {
            return null;
        }

        if ((int) $subscription->current_cycle_step === $cycleStep && $subscription->next_billing_at) {
            return $subscription->next_billing_at->toIso8601String();
        }

        return null;
    }

    private function billingDueNote(array $cycle, ?Subscription $subscription): ?string
    {
        if ($this->cycleBillingDueAt($cycle, $subscription) !== null) {
            return null;
        }

        return 'Historical cycle due dates are not stored in billing_ledger_entries; only active-cycle next_billing_at is shown when it matches cycle_step.';
    }

    /**
     * @param  array<string, mixed>  $cycle
     */
    private function gatewayResponseSummary(array $cycle): ?string
    {
        $lastFailure = collect($cycle['entries'])
            ->reverse()
            ->first(fn ($e) => $e instanceof BillingLedgerEntry && $e->outcome === BillingLedgerOutcome::Failure);

        if ($lastFailure instanceof BillingLedgerEntry && filled($lastFailure->failure_message)) {
            return (string) $lastFailure->failure_message;
        }

        if ($lastFailure instanceof BillingLedgerEntry && filled($lastFailure->failure_code)) {
            return (string) $lastFailure->failure_code;
        }

        $success = $cycle['success_entry'] ?? null;
        if ($success instanceof BillingLedgerEntry && filled($success->transaction_id)) {
            return 'Captured · '.$success->transaction_id;
        }

        return null;
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
}