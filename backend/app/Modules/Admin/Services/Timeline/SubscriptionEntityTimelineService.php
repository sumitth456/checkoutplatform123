<?php

namespace App\Modules\Admin\Services\Timeline;

use App\Filament\Admin\Support\Timeline\CompactTimelinePresenter;
use App\Filament\Admin\Support\Timeline\TimelineEvent;
use App\Filament\Admin\Support\Timeline\TimelineStatus;
use App\Models\BillingLedgerEntry;
use App\Models\Subscription;

final class SubscriptionEntityTimelineService
{
    public function __construct(
        private readonly CompactTimelinePresenter $presenter = new CompactTimelinePresenter(defaultVisible: 6),
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function present(Subscription $subscription): array
    {
        return $this->presenter->present($this->buildEvents($subscription));
    }

    /**
     * @return list<TimelineEvent>
     */
    public function buildEvents(Subscription $subscription): array
    {
        $subscription->loadMissing(['plan', 'adminActivityLogs']);

        $events = [
            new TimelineEvent(
                id: 'sub-created',
                title: 'Subscription created',
                description: $subscription->plan?->name ?? 'Plan linked',
                status: TimelineStatus::Completed,
                timestamp: $subscription->started_at ?? $subscription->created_at,
                details: [
                    'Product' => $subscription->product?->name,
                    'Variant' => $subscription->variant?->sku,
                    'Gateway' => $subscription->paymentGateway?->name,
                ],
            ),
        ];

        if ($subscription->trial_ends_at) {
            $events[] = new TimelineEvent(
                id: 'sub-trial',
                title: 'Trial period',
                description: 'Ends '.$subscription->trial_ends_at->format('M j, Y'),
                status: $subscription->trial_ends_at->isFuture() ? TimelineStatus::Current : TimelineStatus::Completed,
                timestamp: $subscription->trial_ends_at,
            );
        }

        $ledgerGroups = BillingLedgerEntry::query()
            ->where('subscription_id', $subscription->id)
            ->orderBy('attempted_at')
            ->get()
            ->groupBy('cycle_step');

        foreach ($ledgerGroups as $cycle => $entries) {
            $children = [];
            $status = TimelineStatus::Completed;

            foreach ($entries as $entry) {
                $childStatus = in_array($entry->outcome?->value, ['failed', 'failure'], true)
                    ? TimelineStatus::Failed
                    : TimelineStatus::Completed;

                if ($childStatus === TimelineStatus::Failed) {
                    $status = TimelineStatus::Failed;
                }

                $children[] = new TimelineEvent(
                    id: 'cycle-'.$cycle.'-'.$entry->uuid,
                    title: str($entry->outcome?->value ?? 'attempt')->headline(),
                    description: $entry->failure_message ?: 'Retry #'.($entry->retry_attempt ?? 0),
                    status: $childStatus,
                    timestamp: $entry->attempted_at,
                    details: [
                        'Transaction ID' => $entry->transaction_id,
                        'Amount' => number_format((float) $entry->amount, 2).' '.strtoupper((string) $entry->currency),
                    ],
                    collapsedByDefault: true,
                );
            }

            $events[] = new TimelineEvent(
                id: 'billing-cycle-'.$cycle,
                title: 'Billing cycle '.$cycle,
                description: count($children).' attempt(s)',
                status: $status,
                timestamp: $entries->last()?->attempted_at,
                children: $children,
                collapsedByDefault: count($children) > 1,
            );
        }

        if ($subscription->next_billing_at) {
            $events[] = new TimelineEvent(
                id: 'next-charge',
                title: 'Upcoming rebill',
                description: number_format((float) $subscription->amount, 2).' '.strtoupper((string) $subscription->currency),
                status: TimelineStatus::Pending,
                timestamp: $subscription->next_billing_at,
                details: [
                    'Queue status' => $subscription->rebill_queue_status?->value,
                    'Retry count' => (string) ($subscription->retry_count ?? 0),
                    'Dunning stage' => $subscription->dunning_stage,
                ],
            );
        }

        if ($subscription->cancelled_at) {
            $events[] = new TimelineEvent(
                id: 'sub-cancelled',
                title: 'Subscription cancelled',
                description: $subscription->cancellation_reason ?? '',
                status: TimelineStatus::Failed,
                timestamp: $subscription->cancelled_at,
            );
        }

        foreach ($subscription->adminActivityLogs()->latest()->limit(3)->get() as $log) {
            $events[] = new TimelineEvent(
                id: 'admin-'.$log->id,
                title: 'Admin: '.str($log->event)->headline(),
                description: $log->description ?? '',
                status: TimelineStatus::Completed,
                timestamp: $log->created_at,
                collapsedByDefault: true,
            );
        }

        usort($events, fn (TimelineEvent $a, TimelineEvent $b): int => ($a->timestamp instanceof \DateTimeInterface ? $a->timestamp->getTimestamp() : 0)
            <=> ($b->timestamp instanceof \DateTimeInterface ? $b->timestamp->getTimestamp() : 0));

        return $events;
    }
}
