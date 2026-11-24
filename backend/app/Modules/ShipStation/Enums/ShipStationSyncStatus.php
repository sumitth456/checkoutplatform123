<?php

namespace App\Modules\ShipStation\Enums;

enum ShipStationSyncStatus: string
{
    case Pending = 'pending';
    case Synced = 'synced';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Synced => 'Synced',
            self::Failed => 'Sync Failed',
            self::Skipped => 'Skipped',
        };
    }
}
