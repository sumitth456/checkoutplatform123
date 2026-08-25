<?php

namespace App\Filament\Admin\Support\Timeline;

final class TimelineEvent
{
    /**
     * @param  array<string, string|null>  $details
     * @param  list<TimelineEvent>  $children
     */
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly string $description,
        public readonly TimelineStatus $status,
        public readonly mixed $timestamp = null,
        public readonly ?string $icon = null,
        public readonly array $details = [],
        public readonly array $children = [],
        public readonly bool $collapsedByDefault = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'dot_class' => $this->status->dotClass(),
            'badge_class' => $this->status->badgeClass(),
            'timestamp' => $this->formatTimestamp(),
            'icon' => $this->icon,
            'details' => $this->details,
            'children' => array_map(fn (self $child): array => $child->toArray(), $this->children),
            'collapsed' => $this->collapsedByDefault,
        ];
    }

    private function formatTimestamp(): ?string
    {
        if ($this->timestamp instanceof \DateTimeInterface) {
            return $this->timestamp->format('M j, Y H:i:s');
        }

        return is_string($this->timestamp) && $this->timestamp !== '' ? $this->timestamp : null;
    }
}
