<?php

namespace App\Filament\Admin\Support\Timeline;

final class CompactTimelinePresenter
{
    public function __construct(
        private readonly int $defaultVisible = 6,
    ) {}

    /**
     * @param  list<TimelineEvent>  $events
     * @return array{events: list<array<string, mixed>>, hidden_count: int, total: int}
     */
    public function present(array $events): array
    {
        $normalized = array_map(fn (TimelineEvent $event): array => $event->toArray(), $events);
        $total = count($normalized);

        if ($total <= $this->defaultVisible) {
            return [
                'events' => $normalized,
                'hidden_count' => 0,
                'total' => $total,
            ];
        }

        $visible = array_slice($normalized, max(0, $total - $this->defaultVisible));

        return [
            'events' => $visible,
            'hidden_count' => $total - count($visible),
            'total' => $total,
            'older_events' => array_slice($normalized, 0, max(0, $total - $this->defaultVisible)),
        ];
    }
}
