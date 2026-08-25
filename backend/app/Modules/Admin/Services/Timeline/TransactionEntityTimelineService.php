<?php

namespace App\Modules\Admin\Services\Timeline;

use App\Filament\Admin\Support\Timeline\CompactTimelinePresenter;
use App\Filament\Admin\Support\Timeline\TimelineEvent;
use App\Filament\Admin\Support\Timeline\TimelineStatus;
use App\Models\Transaction;

final class TransactionEntityTimelineService
{
    public function __construct(
        private readonly CompactTimelinePresenter $presenter = new CompactTimelinePresenter(defaultVisible: 5),
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function present(Transaction $transaction): array
    {
        return $this->presenter->present($this->buildEvents($transaction));
    }

    /**
     * @return list<TimelineEvent>
     */
    public function buildEvents(Transaction $transaction): array
    {
        $transaction->loadMissing(['paymentGateway', 'parentTransaction', 'adminActivityLogs']);

        $events = [
            new TimelineEvent(
                id: 'txn-created',
                title: 'Transaction initiated',
                description: str($transaction->type)->headline(),
                status: TimelineStatus::Completed,
                timestamp: $transaction->created_at,
                details: [
                    'Amount' => number_format((float) $transaction->amount, 2).' '.strtoupper((string) $transaction->currency),
                    'Gateway' => $transaction->paymentGateway?->name,
                ],
            ),
        ];

        $events[] = new TimelineEvent(
            id: 'txn-'.str($transaction->type)->slug(),
            title: str($transaction->type)->headline(),
            description: str($transaction->status)->headline(),
            status: in_array($transaction->status, ['failed', 'declined'], true)
                ? TimelineStatus::Failed
                : TimelineStatus::Completed,
            timestamp: $transaction->processed_at ?? $transaction->updated_at,
            details: [
                'Transaction ID' => $transaction->external_id ?: $transaction->uuid,
                'Failure' => $transaction->failure_message,
                'Gateway response' => $this->summarize($transaction->gateway_response),
            ],
        );

        if ($transaction->parentTransaction) {
            $events[] = new TimelineEvent(
                id: 'parent-'.$transaction->parentTransaction->uuid,
                title: 'Parent transaction',
                description: str($transaction->parentTransaction->type)->headline(),
                status: TimelineStatus::Completed,
                timestamp: $transaction->parentTransaction->processed_at,
                collapsedByDefault: true,
            );
        }

        foreach ($transaction->adminActivityLogs()->latest()->limit(3)->get() as $log) {
            $events[] = new TimelineEvent(
                id: 'admin-'.$log->id,
                title: 'Admin: '.str($log->event)->headline(),
                description: $log->description ?? '',
                status: TimelineStatus::Completed,
                timestamp: $log->created_at,
                collapsedByDefault: true,
            );
        }

        return $events;
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function summarize(?array $payload): ?string
    {
        if ($payload === null || $payload === []) {
            return null;
        }

        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES);

        return $encoded === false ? null : (strlen($encoded) > 180 ? substr($encoded, 0, 180).'…' : $encoded);
    }
}
