<?php

namespace App\Filament\Admin\Resources\FulfillmentHouses\Pages;

use App\Filament\Admin\Resources\FulfillmentHouses\FulfillmentHouseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFulfillmentHouses extends ListRecords
{
    protected static string $resource = FulfillmentHouseResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}