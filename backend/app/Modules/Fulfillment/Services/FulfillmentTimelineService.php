<?php

namespace App\Modules\Fulfillment\Services;

use App\Models\Fulfillment;
use App\Models\FulfillmentTimelineEvent;
use App\Modules\Order\Enums\FulfillmentRecordStatus;
use Illuminate\Support\Facades\Auth;

class FulfillmentTimelineService
{
    public function record(
        Fulfillment $fulfillment,
        string $eventType,
        string $title,
        ?string $description = null,
        ?string $statusLabel = null,
        ?array $metadata = null,
        ?string $actorName = null,
    ): FulfillmentTimelineEvent {
        return FulfillmentTimelineEvent::query()->create([
            'fulfillment_id' => $fulfillment->id,
            'event_type' => $eventType,
            'title' => $title,
            'description' => $description,
            'status_label' => $statusLabel ?? $fulfillment->status?->label(),
            'actor_type' => Auth::check() ? 'user' : 'system',
            'actor_name' => $actorName ?? Auth::user()?->name ?? 'System',
            'metadata' => $metadata,
            'occurred_at' => now(),
        ]);
    }

    public function recordStatusChange(
        Fulfillment $fulfillment,
        FulfillmentRecordStatus $from,
        FulfillmentRecordStatus $to,
        ?string $description = null,
    ): FulfillmentTimelineEvent {
        return $this->record(
            fulfillment: $fulfillment,
            eventType: 'status_change',
            title: $from->label().' → '.$to->label(),
            description: $description,
            statusLabel: $to->label(),
            metadata: ['from' => $from->value, 'to' => $to->value],
        );
    }

    /** @return list<array<string, mixed>> */
    public function buildSyntheticTimeline(Fulfillment $fulfillment): array
    {
        $events = [];

        if ($fulfillment->order) {
            $events[] = [
                'title' => 'Order Created',
                'description' => 'Order '.$fulfillment->order->order_number.' placed',
                'occurred_at' => $fulfillment->order->created_at,
                'status_label' => 'Created',
            ];
            $events[] = [
                'title' => 'Payment Successful',
                'description' => 'Order payment captured',
                'occurred_at' => $fulfillment->order->paid_at ?? $fulfillment->order->created_at,
                'status_label' => 'Paid',
            ];
        }

        $events[] = [
            'title' => 'Fulfillment Created',
            'description' => 'Fulfillment record '.$fulfillment->fulfillment_number.' created',
            'occurred_at' => $fulfillment->created_at,
            'status_label' => FulfillmentRecordStatus::Pending->label(),
        ];

        foreach ([
            ['field' => 'queued_at', 'title' => 'Queued'],
            ['field' => 'on_hold_at', 'title' => 'On Hold'],
            ['field' => 'processing_at', 'title' => 'Processing'],
            ['field' => 'packed_at', 'title' => 'Packed'],
            ['field' => 'ready_to_ship_at', 'title' => 'Ready To Ship'],
            ['field' => 'shipped_at', 'title' => 'Shipped'],
            ['field' => 'delivered_at', 'title' => 'Delivered'],
            ['field' => 'returned_at', 'title' => 'Returned'],
            ['field' => 'cancelled_at', 'title' => 'Cancelled'],
            ['field' => 'failed_at', 'title' => 'Failed'],
        ] as $step) {
            $at = $fulfillment->{$step['field']};
            if ($at !== null) {
                $events[] = [
                    'title' => $step['title'],
                    'description' => null,
                    'occurred_at' => $at,
                    'status_label' => $step['title'],
                ];
            }
        }

        return collect($events)->sortBy('occurred_at')->values()->all();
    }
}
