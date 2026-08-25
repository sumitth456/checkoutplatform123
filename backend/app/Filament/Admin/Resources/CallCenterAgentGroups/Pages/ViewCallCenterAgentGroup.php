<?php

namespace App\Filament\Admin\Resources\CallCenterAgentGroups\Pages;

use App\Filament\Admin\Resources\CallCenterAgentGroups\CallCenterAgentGroupResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCallCenterAgentGroup extends ViewRecord
{
    protected static string $resource = CallCenterAgentGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
