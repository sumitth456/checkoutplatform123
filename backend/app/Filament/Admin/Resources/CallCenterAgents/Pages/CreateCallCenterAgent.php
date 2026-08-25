<?php

namespace App\Filament\Admin\Resources\CallCenterAgents\Pages;

use App\Filament\Admin\Resources\CallCenterAgents\CallCenterAgentResource;
use App\Filament\Admin\Resources\CallCenterAgents\Concerns\LogsCallCenterAgentAdminActivity;
use Filament\Resources\Pages\CreateRecord;

class CreateCallCenterAgent extends CreateRecord
{
    use LogsCallCenterAgentAdminActivity;

    protected static string $resource = CallCenterAgentResource::class;

    protected function afterCreate(): void
    {
        $this->logCallCenterAgentActivity('created', "Agent {$this->record->name} created");
    }
}
