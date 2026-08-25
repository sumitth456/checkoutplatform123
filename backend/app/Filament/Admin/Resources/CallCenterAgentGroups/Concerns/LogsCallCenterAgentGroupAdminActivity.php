<?php

namespace App\Filament\Admin\Resources\CallCenterAgentGroups\Concerns;

use App\Models\CallCenterAgentGroup;
use App\Support\Admin\AdminActivityLogger;

trait LogsCallCenterAgentGroupAdminActivity
{
    protected function logCallCenterAgentGroupActivity(string $event, ?string $description = null, array $properties = []): void
    {
        if (! $this->record instanceof CallCenterAgentGroup) {
            return;
        }

        AdminActivityLogger::log(
            module: 'call_center_agent_groups',
            event: $event,
            description: $description,
            subject: $this->record,
            properties: $properties,
        );
    }
}
