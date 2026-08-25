<?php

namespace App\Modules\Admin\Services;

use App\Models\Subscription;
use App\Modules\Subscription\Enums\BillingLedgerOutcome;
use App\Modules\Subscription\Enums\RebillQueueStatus;
use App\Modules\Subscription\Enums\SubscriptionStatus;
use App\Modules\Subscription\Services\SubscriptionEngineService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class RebillQueueService
{
    public function upcomingQuery(): Builder
    {
        return $this->baseQueueQuery()
            ->where('next_billing_at', '>', now());
    }

    public function dueNowQuery(): Builder
    {
        return $this->baseQueueQuery()
            ->whereNotNull('next_billing_at')
            ->where('next_billing_at', '<=', now());
    }

    public function failedQuery(): Builder
    {
        return Subscription::query()
            ->with(['customer', 'plan'])
            ->where('rebill_queue_status', RebillQueueStatus::Queued)
            ->where(function (Builder $query): void {
                $query->where('status', SubscriptionStatus::PastDue)
                    ->orWhereHas('billingLedgerEntries', function (Builder $ledger): void {
                        $ledger->where('outcome', BillingLedgerOutcome::Failure)
                            ->where('attempted_at', '>=', now()->subDays(30));
                    });
            });
    }

    public function retriesQuery(): Builder
    {
        return $this->baseQueueQuery()
            ->where('retry_count', '>', 0)
            ->whereIn('status', [
                SubscriptionStatus::PastDue,
                SubscriptionStatus::Active,
                SubscriptionStatus::Trial,
            ]);
    }

    public function skipCharge(Subscription $subscription, CarbonInterface $until): Subscription
    {
        $subscription->rebill_queue_status = RebillQueueStatus::Skipped;
        $subscription->rebill_skipped_until = $until;
        $subscription->next_billing_at = $until;
        $subscription->current_period_ends_at = $until;
        $subscription->save();

        return $subscription->fresh();
    }

    public function resumeQueue(Subscription $subscription): Subscription
    {
        $subscription->rebill_queue_status = RebillQueueStatus::Queued;
        $subscription->rebill_skipped_until = null;
        $subscription->save();

        return $subscription->fresh();
    }

    public function cancelRebill(Subscription $subscription, ?string $reason = null): Subscription
    {
        $subscription->rebill_queue_status = RebillQueueStatus::Cancelled;
        $subscription->rebill_skipped_until = null;
        $subscription->save();

        return app(SubscriptionEngineService::class)->cancel(
            $subscription->fresh(),
            $reason ?? 'rebill_cancelled_admin',
        );
    }

    public function counts(): array
    {
        return [
            'upcoming' => (int) $this->upcomingQuery()->count(),
            'due' => (int) $this->dueNowQuery()->count(),
            'failed' => (int) $this->failedQuery()->count(),
            'retries' => (int) $this->retriesQuery()->count(),
        ];
    }

    protected function baseQueueQuery(): Builder
    {
        return Subscription::query()
            ->with(['customer', 'plan'])
            ->where('rebill_queue_status', RebillQueueStatus::Queued)
            ->whereIn('status', [
                SubscriptionStatus::Trial,
                SubscriptionStatus::Active,
                SubscriptionStatus::PastDue,
            ])
            ->whereNotNull('next_billing_at');
    }
}
