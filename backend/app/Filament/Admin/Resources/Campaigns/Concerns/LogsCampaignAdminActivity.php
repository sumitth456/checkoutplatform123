<?php

namespace App\Filament\Admin\Resources\Campaigns\Concerns;

use App\Models\Campaign;
use App\Support\Admin\AdminActivityLogger;

trait LogsCampaignAdminActivity
{
    protected function logCampaignActivity(string $event, ?string $description = null, array $properties = []): void
    {
        if (! $this->record instanceof Campaign) {
            return;
        }

        AdminActivityLogger::log(
            module: 'campaigns',
            event: $event,
            description: $description,
            subject: $this->record,
            properties: $properties,
        );
    }
}
