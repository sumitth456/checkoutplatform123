<?php

namespace App\Filament\Admin\Resources\CallDispositions\Concerns;

use App\Models\CallDisposition;
use App\Support\Admin\AdminActivityLogger;

trait LogsCallDispositionAdminActivity
{
    protected function logCallDispositionActivity(string $event, ?string $description = null, array $properties = []): void
    {
        if (! $this->record instanceof CallDisposition) {
            return;
        }

        AdminActivityLogger::log(
            module: 'call_dispositions',
            event: $event,
            description: $description,
            subject: $this->record,
            properties: $properties,
        );
    }
}
