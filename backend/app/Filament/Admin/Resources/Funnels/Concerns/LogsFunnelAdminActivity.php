<?php

namespace App\Filament\Admin\Resources\Funnels\Concerns;

use App\Models\Funnel;
use App\Support\Admin\AdminActivityLogger;

trait LogsFunnelAdminActivity
{
    protected function logFunnelActivity(string $event, ?string $description = null, array $properties = []): void
    {
        if (! $this->record instanceof Funnel) {
            return;
        }

        AdminActivityLogger::log(
            module: 'funnels',
            event: $event,
            description: $description,
            subject: $this->record,
            properties: $properties,
        );
    }
}