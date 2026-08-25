<?php

namespace App\Filament\Admin\Resources\CallCenterAgents\Pages;

use App\Filament\Admin\Resources\CallCenterAgents\CallCenterAgentResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCallCenterAgent extends ViewRecord
{
    protected static string $resource = CallCenterAgentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
