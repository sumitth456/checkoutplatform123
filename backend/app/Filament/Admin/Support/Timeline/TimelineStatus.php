<?php

namespace App\Filament\Admin\Support\Timeline;

enum TimelineStatus: string
{
    case Completed = 'completed';
    case Current = 'current';
    case Pending = 'pending';
    case Retry = 'retry';
    case Failed = 'failed';

    public function dotClass(): string
    {
        return match ($this) {
            self::Completed => 'cp-timeline-dot--completed',
            self::Current => 'cp-timeline-dot--current',
            self::Pending => 'cp-timeline-dot--pending',
            self::Retry => 'cp-timeline-dot--retry',
            self::Failed => 'cp-timeline-dot--failed',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Completed => 'cp-badge--success',
            self::Current => 'cp-badge--info',
            self::Pending => 'cp-badge--muted',
            self::Retry => 'cp-badge--warning',
            self::Failed => 'cp-badge--danger',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Completed => 'Completed',
            self::Current => 'Current',
            self::Pending => 'Pending',
            self::Retry => 'Retry scheduled',
            self::Failed => 'Failed',
        };
    }
}
