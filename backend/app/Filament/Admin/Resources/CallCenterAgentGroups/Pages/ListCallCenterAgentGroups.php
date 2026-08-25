<?php

namespace App\Filament\Admin\Resources\CallCenterAgentGroups\Pages;

use App\Filament\Admin\Resources\CallCenterAgentGroups\CallCenterAgentGroupResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCallCenterAgentGroups extends ListRecords
{
    protected static string $resource = CallCenterAgentGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
