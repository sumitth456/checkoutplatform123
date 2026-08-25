<?php

namespace App\Modules\Admin\Services\Timeline;

use App\Filament\Admin\Support\Timeline\CompactTimelinePresenter;
use App\Filament\Admin\Support\Timeline\TimelineEvent;
use App\Filament\Admin\Support\Timeline\TimelineStatus;
use App\Models\BillingLedgerEntry;
use App\Models\Order;
use App\Modules\Admin\Services\OrderRebillDetailsService;
use App\Modules\Admin\Services\OrderTimelineService;

final class OrderEntityTimelineService
{
    public function __construct(
        private readonly OrderTimelineService $timeline,
        private readonly OrderRebillDetailsService $rebillDetails,
        private readonly CompactTimelinePresenter $presenter = new CompactTimelinePresenter(defaultVisible: 6),
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function present(Order $order): array
    {
        $events = $this->buildEvents($order);

        return $this->presenter->present($events);
    }

    /**
     * @return list<TimelineEvent>
     */
    public function buildEvents(Order $order): array
    {
        $events = [];

        foreach ($this->timeline->entries($order) as $entry) {
            $events[] = new TimelineEvent(
                id: 'timeline-'.$entry->id,
                title: $entry->title,
                description: $entry->detail ?? '',
                status: $this->statusForType($entry->type),
                timestamp: $entry->occurred_at,
                details: ['Type' => str($entry->type)->headline()],
            );
        }

        foreach ($this->groupedRebillEvents($order) as $group) {
            $events[] = $group;
        }

        usort($events, fn (TimelineEvent $a, TimelineEvent $b): int => $this->timestampSort($a->timestamp) <=> $this->timestampSort($b->timestamp));

        if ($events !== []) {
            $lastIndex = array_key_last($events);
            $last = $events[$lastIndex];
            $events[$lastIndex] = new TimelineEvent(
                id: $last->id,
                title: $last->title,
                description: $last->description,
                status: TimelineStatus::Current,
                timestamp: $last->timestamp,
                icon: $last->icon,
                details: $last->details,
                children: $last->children,
                collapsedByDefault: $last->collapsedByDefault,
            );
        }

        return $events;
    }

    /**
     * @return list<TimelineEvent>
     */
    private function groupedRebillEvents(Order $order): array
    {
        $groups = [];
        $subscriptionIds = $order->subscriptions()->pluck('id');

        if ($subscriptionIds->isEmpty()) {
            return [];
        }

        $entries = BillingLedgerEntry::query()
            ->whereIn('subscription_id', $subscriptionIds)
            ->orderBy('attempted_at')
            ->get()
            ->groupBy('cycle_step');

        foreach ($entries as $cycle => $cycleEntries) {
            $children = [];
            $overallStatus = TimelineStatus::Completed;

            foreach ($cycleEntries as $entry) {
                $childStatus = match ($entry->outcome?->value) {
                    'success', 'succeeded' => TimelineStatus::Completed,
                    'failed', 'failure' => TimelineStatus::Failed,
                    default => TimelineStatus::Pending,
                };

                if ($childStatus === TimelineStatus::Failed) {
                    $overallStatus = TimelineStatus::Failed;
                }

                $children[] = new TimelineEvent(
                    id: 'rebill-'.$entry->uuid,
                    title: 'Attempt #'.($entry->retry_attempt ?? 0),
                    description: $entry->failure_message ?: str($entry->outcome?->value ?? 'attempt')->headline(),
                    status: $childStatus,
                    timestamp: $entry->attempted_at,
                    details: [
                        'Amount' => number_format((float) $entry->amount, 2).' '.strtoupper((string) $entry->currency),
                        'Transaction ID' => $entry->transaction_id,
                        'Failure code' => $entry->failure_code,
                    ],
                    collapsedByDefault: true,
                );
            }

            $groups[] = new TimelineEvent(
                id: 'rebill-cycle-'.$cycle,
                title: 'Rebill #'.$cycle,
                description: count($children).' payment event(s)',
                status: $overallStatus,
                timestamp: $cycleEntries->last()?->attempted_at,
                children: $children,
                collapsedByDefault: count($children) > 1,
            );
        }

        foreach ($this->rebillDetails->rebillQueueRows($order) as $row) {
            $groups[] = new TimelineEvent(
                id: 'rebill-queue-'.$row['subscription_uuid'],
                title: 'Rebill scheduled',
                description: ($row['product'] ?? 'Subscription').' · '.$row['amount'],
                status: TimelineStatus::Pending,
                timestamp: $row['next_rebill_date'],
                details: [
                    'Billing cycle' => $row['billing_cycle'],
                    'Gateway' => $row['gateway'],
                    'Queue status' => $row['queue_status'],
                    'Retry count' => $row['retry_count'],
                ],
            );
        }

        return $groups;
    }

    private function statusForType(string $type): TimelineStatus
    {
        if (str_contains($type, 'fail') || str_contains($type, 'cancel')) {
            return TimelineStatus::Failed;
        }

        if (str_contains($type, 'refund')) {
            return TimelineStatus::Retry;
        }

        return TimelineStatus::Completed;
    }

    private function timestampSort(mixed $timestamp): int
    {
        return $timestamp instanceof \DateTimeInterface ? $timestamp->getTimestamp() : 0;
    }
}
