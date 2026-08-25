<?php

namespace App\Modules\Admin\Services\Timeline;

use App\Filament\Admin\Support\Timeline\CompactTimelinePresenter;
use App\Filament\Admin\Support\Timeline\TimelineEvent;
use App\Filament\Admin\Support\Timeline\TimelineStatus;
use App\Models\Customer;

final class CustomerEntityTimelineService
{
    public function __construct(
        private readonly CompactTimelinePresenter $presenter = new CompactTimelinePresenter(defaultVisible: 6),
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function present(Customer $customer): array
    {
        return $this->presenter->present($this->buildEvents($customer));
    }

    /**
     * @return list<TimelineEvent>
     */
    public function buildEvents(Customer $customer): array
    {
        $customer->loadMissing(['orders', 'subscriptions', 'transactions', 'adminActivityLogs']);

        $events = [
            new TimelineEvent(
                id: 'customer-created',
                title: 'Customer created',
                description: $customer->email,
                status: TimelineStatus::Completed,
                timestamp: $customer->created_at,
            ),
        ];

        foreach ($customer->orders()->latest('placed_at')->limit(5)->get() as $order) {
            $events[] = new TimelineEvent(
                id: 'order-'.$order->uuid,
                title: 'Order '.$order->order_number,
                description: number_format((float) $order->grand_total, 2).' '.strtoupper((string) $order->currency),
                status: TimelineStatus::Completed,
                timestamp: $order->placed_at ?? $order->created_at,
                details: [
                    'Status' => str($order->status->value ?? $order->status)->headline(),
                    'Payment' => str($order->payment_status->value ?? $order->payment_status)->headline(),
                ],
                collapsedByDefault: true,
            );
        }

        foreach ($customer->subscriptions()->latest()->limit(3)->get() as $subscription) {
            $events[] = new TimelineEvent(
                id: 'subscription-'.$subscription->uuid,
                title: 'Subscription '.str($subscription->status->value ?? $subscription->status)->headline(),
                description: $subscription->plan?->name ?? 'Subscription',
                status: TimelineStatus::Completed,
                timestamp: $subscription->started_at ?? $subscription->created_at,
                details: [
                    'Next billing' => $subscription->next_billing_at?->format('M j, Y H:i'),
                    'Amount' => number_format((float) $subscription->amount, 2).' '.strtoupper((string) $subscription->currency),
                ],
                collapsedByDefault: true,
            );
        }

        foreach ($customer->transactions()->latest('processed_at')->limit(5)->get() as $transaction) {
            $failed = in_array($transaction->status, ['failed', 'declined'], true);
            $events[] = new TimelineEvent(
                id: 'txn-'.$transaction->uuid,
                title: str($transaction->type)->headline(),
                description: number_format((float) $transaction->amount, 2).' '.strtoupper((string) $transaction->currency),
                status: $failed ? TimelineStatus::Failed : TimelineStatus::Completed,
                timestamp: $transaction->processed_at ?? $transaction->created_at,
                details: [
                    'Status' => str($transaction->status)->headline(),
                    'Gateway' => $transaction->paymentGateway?->name,
                ],
                collapsedByDefault: true,
            );
        }

        foreach ($customer->adminActivityLogs()->latest()->limit(5)->get() as $log) {
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
