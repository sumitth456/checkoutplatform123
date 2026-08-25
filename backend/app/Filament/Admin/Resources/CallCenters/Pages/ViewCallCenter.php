<?php

namespace App\Filament\Admin\Resources\CallCenters\Pages;

use App\Filament\Admin\Resources\CallCenters\CallCenterResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCallCenter extends ViewRecord
{
    protected static string $resource = CallCenterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
