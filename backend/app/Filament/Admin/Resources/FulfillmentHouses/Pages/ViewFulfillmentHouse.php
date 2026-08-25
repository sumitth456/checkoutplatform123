<?php

namespace App\Filament\Admin\Resources\FulfillmentHouses\Pages;

use App\Filament\Admin\Resources\FulfillmentHouses\FulfillmentHouseResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewFulfillmentHouse extends ViewRecord
{
    protected static string $resource = FulfillmentHouseResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }
}