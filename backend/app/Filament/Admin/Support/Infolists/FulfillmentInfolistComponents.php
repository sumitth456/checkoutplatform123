<?php

namespace App\Filament\Admin\Support\Infolists;

use App\Models\Fulfillment;
use App\Models\FulfillmentTimelineEvent;
use App\Modules\Order\Enums\FulfillmentRecordStatus;
use Filament\Schemas\Components\View as SchemaView;

final class FulfillmentInfolistComponents
{
    public static function fulfillmentTimeline(?string $heading = 'Fulfillment timeline'): SchemaView
    {
        return SchemaView::make('filament.admin.components.fulfillment-timeline')
            ->columnSpanFull()
            ->viewData(fn (Fulfillment $record): array => [
                'heading' => $heading,
                'timeline' => self::presentTimeline($record),
            ]);
    }

    /**
     * @return array{events: list<array<string, mixed>>, older_events: list<array<string, mixed>>, hidden_count: int}
     */
    public static function presentTimeline(Fulfillment $record): array
    {
        $record->loadMissing('timelineEvents');

        $events = $record->timelineEvents->map(
            fn (FulfillmentTimelineEvent $event): array => self::formatTimelineEvent($event),
        )->all();

        if ($events === []) {
            $events = self::syntheticTimelineFromFulfillment($record);
        }

        $visible = array_slice($events, 0, 8);
        $older = array_slice($events, 8);

        return [
            'events' => $visible,
            'older_events' => $older,
            'hidden_count' => count($older),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function formatTimelineEvent(FulfillmentTimelineEvent $event): array
    {
        return [
            'title' => $event->title,
            'description' => $event->description,
            'status_label' => $event->status_label ?? str($event->event_type)->headline(),
            'timestamp' => $event->occurred_at?->timezone(config('app.timezone'))->format('M j, Y g:i A'),
            'badge_class' => self::badgeClassForType($event->event_type),
            'dot_class' => self::dotClassForType($event->event_type),
            'details' => collect($event->metadata ?? [])
                ->mapWithKeys(fn ($value, $key): array => [str($key)->headline()->toString() => is_scalar($value) ? (string) $value : json_encode($value)])
                ->all(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function syntheticTimelineFromFulfillment(Fulfillment $record): array
    {
        $events = [];

        $events[] = [
            'title' => 'Fulfillment created',
            'description' => "Scheduled for {$record->scheduled_ship_date->format('M j, Y')}",
            'status_label' => 'Scheduled',
            'timestamp' => $record->created_at?->timezone(config('app.timezone'))->format('M j, Y g:i A'),
            'badge_class' => 'cp-badge--info',
            'dot_class' => 'cp-timeline-dot--pending',
            'details' => [
                'Delay days' => (string) $record->delay_days,
                'UUID' => $record->uuid,
            ],
        ];

        if ($record->status !== FulfillmentRecordStatus::Scheduled) {
            $events[] = [
                'title' => 'Status: '.str($record->status->name)->headline(),
                'description' => $record->failure_reason,
                'status_label' => str($record->status->name)->headline(),
                'timestamp' => $record->updated_at?->timezone(config('app.timezone'))->format('M j, Y g:i A'),
                'badge_class' => self::badgeClassForStatus($record->status),
                'dot_class' => self::dotClassForStatus($record->status),
                'details' => array_filter([
                    'Warehouse ref' => $record->warehouse_reference,
                    'Tracking' => $record->tracking_number,
                    'Carrier' => $record->carrier,
                ]),
            ];
        }

        if ($record->shipped_at !== null) {
            $events[] = [
                'title' => 'Shipped',
                'description' => $record->tracking_number ? "Tracking {$record->tracking_number}" : null,
                'status_label' => 'Shipped',
                'timestamp' => $record->shipped_at->timezone(config('app.timezone'))->format('M j, Y g:i A'),
                'badge_class' => 'cp-badge--success',
                'dot_class' => 'cp-timeline-dot--success',
                'details' => array_filter([
                    'Carrier' => $record->carrier,
                    'Shipment status' => $record->shipment_status?->name,
                ]),
            ];
        }

        return array_reverse($events);
    }

    private static function badgeClassForType(?string $type): string
    {
        return match ($type) {
            'shipped', 'delivered' => 'cp-badge--success',
            'failed', 'cancelled', 'returned' => 'cp-badge--danger',
            'submitted', 'delayed' => 'cp-badge--warning',
            default => 'cp-badge--muted',
        };
    }

    private static function dotClassForType(?string $type): string
    {
        return match ($type) {
            'shipped', 'delivered' => 'cp-timeline-dot--success',
            'failed', 'cancelled', 'returned' => 'cp-timeline-dot--danger',
            default => 'cp-timeline-dot--pending',
        };
    }

    private static function badgeClassForStatus(FulfillmentRecordStatus $status): string
    {
        return match ($status) {
            FulfillmentRecordStatus::Shipped, FulfillmentRecordStatus::Delivered => 'cp-badge--success',
            FulfillmentRecordStatus::Failed, FulfillmentRecordStatus::Cancelled => 'cp-badge--danger',
            FulfillmentRecordStatus::Submitted => 'cp-badge--warning',
            default => 'cp-badge--info',
        };
    }

    private static function dotClassForStatus(FulfillmentRecordStatus $status): string
    {
        return match ($status) {
            FulfillmentRecordStatus::Shipped, FulfillmentRecordStatus::Delivered => 'cp-timeline-dot--success',
            FulfillmentRecordStatus::Failed, FulfillmentRecordStatus::Cancelled => 'cp-timeline-dot--danger',
            default => 'cp-timeline-dot--pending',
        };
    }
}
