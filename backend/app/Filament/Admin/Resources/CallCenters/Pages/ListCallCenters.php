<?php

namespace App\Filament\Admin\Resources\CallCenters\Pages;

use App\Filament\Admin\Resources\CallCenters\CallCenterResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCallCenters extends ListRecords
{
    protected static string $resource = CallCenterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
