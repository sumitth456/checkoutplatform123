<?php

namespace App\Modules\Admin\Services\Timeline;

use App\Filament\Admin\Support\Timeline\CompactTimelinePresenter;
use App\Filament\Admin\Support\Timeline\TimelineEvent;
use App\Filament\Admin\Support\Timeline\TimelineStatus;
use Illuminate\Database\Eloquent\Model;

final class GenericEntityTimelineService
{
    public function __construct(
        private readonly CompactTimelinePresenter $presenter = new CompactTimelinePresenter(defaultVisible: 5),
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function present(Model $record): array
    {
        return $this->presenter->present($this->buildEvents($record));
    }

    /**
     * @return list<TimelineEvent>
     */
    public function buildEvents(Model $record): array
    {
        $events = [
            new TimelineEvent(
                id: 'created',
                title: class_basename($record).' created',
                description: method_exists($record, 'getFilamentName') ? $record->getFilamentName() : ($record->name ?? $record->uuid ?? ''),
                status: TimelineStatus::Completed,
                timestamp: $record->created_at ?? null,
            ),
        ];

        if (method_exists($record, 'adminActivityLogs')) {
            foreach ($record->adminActivityLogs()->latest()->limit(5)->get() as $log) {
                $events[] = new TimelineEvent(
                    id: 'admin-'.$log->id,
                    title: 'Admin: '.str($log->event)->headline(),
                    description: $log->description ?? '',
                    status: TimelineStatus::Completed,
                    timestamp: $log->created_at,
                    collapsedByDefault: true,
                );
            }
        }

        if ($record->updated_at && $record->created_at && ! $record->updated_at->equalTo($record->created_at)) {
            $events[] = new TimelineEvent(
                id: 'updated',
                title: 'Updated',
                description: 'Record last modified',
                status: TimelineStatus::Current,
                timestamp: $record->updated_at,
            );
        }

        if (isset($record->archived_at) && $record->archived_at) {
            $events[] = new TimelineEvent(
                id: 'archived',
                title: 'Archived',
                description: '',
                status: TimelineStatus::Completed,
                timestamp: $record->archived_at,
            );
        }

        if (isset($record->published_at) && $record->published_at) {
            $events[] = new TimelineEvent(
                id: 'published',
                title: 'Published',
                description: '',
                status: TimelineStatus::Completed,
                timestamp: $record->published_at,
            );
        }

        usort($events, fn (TimelineEvent $a, TimelineEvent $b): int => ($a->timestamp instanceof \DateTimeInterface ? $a->timestamp->getTimestamp() : 0)
            <=> ($b->timestamp instanceof \DateTimeInterface ? $b->timestamp->getTimestamp() : 0));

        return $events;
    }
}
