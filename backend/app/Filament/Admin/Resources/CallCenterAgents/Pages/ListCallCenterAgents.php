<?php

namespace App\Filament\Admin\Resources\CallCenterAgents\Pages;

use App\Filament\Admin\Resources\CallCenterAgents\CallCenterAgentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCallCenterAgents extends ListRecords
{
    protected static string $resource = CallCenterAgentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
