<?php

namespace App\Filament\Admin\Resources\CallCenters\Concerns;

use App\Models\CallCenter;
use App\Support\Admin\AdminActivityLogger;

trait LogsCallCenterAdminActivity
{
    protected function logCallCenterActivity(string $event, ?string $description = null, array $properties = []): void
    {
        if (! $this->record instanceof CallCenter) {
            return;
        }

        AdminActivityLogger::log(
            module: 'call_centers',
            event: $event,
            description: $description,
            subject: $this->record,
            properties: $properties,
        );
    }
}
