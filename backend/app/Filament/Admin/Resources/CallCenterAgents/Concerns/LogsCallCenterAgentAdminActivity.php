<?php

namespace App\Filament\Admin\Resources\CallCenterAgents\Concerns;

use App\Models\CallCenterAgent;
use App\Support\Admin\AdminActivityLogger;

trait LogsCallCenterAgentAdminActivity
{
    protected function logCallCenterAgentActivity(string $event, ?string $description = null, array $properties = []): void
    {
        if (! $this->record instanceof CallCenterAgent) {
            return;
        }

        AdminActivityLogger::log(
            module: 'call_center_agents',
            event: $event,
            description: $description,
            subject: $this->record,
            properties: $properties,
        );
    }
}
