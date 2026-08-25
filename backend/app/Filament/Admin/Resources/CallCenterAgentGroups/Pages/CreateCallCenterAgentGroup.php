<?php

namespace App\Filament\Admin\Resources\CallCenterAgentGroups\Pages;

use App\Filament\Admin\Resources\CallCenterAgentGroups\CallCenterAgentGroupResource;
use App\Filament\Admin\Resources\CallCenterAgentGroups\Concerns\LogsCallCenterAgentGroupAdminActivity;
use Filament\Resources\Pages\CreateRecord;

class CreateCallCenterAgentGroup extends CreateRecord
{
    use LogsCallCenterAgentGroupAdminActivity;

    protected static string $resource = CallCenterAgentGroupResource::class;

    protected function afterCreate(): void
    {
        $this->logCallCenterAgentGroupActivity('created', "Agent group {$this->record->name} created");
    }
}
