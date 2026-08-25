<?php

namespace App\Filament\Admin\Resources\FulfillmentHouses\Pages;

use App\Filament\Admin\Resources\FulfillmentHouses\FulfillmentHouseResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFulfillmentHouse extends CreateRecord
{
    protected static string $resource = FulfillmentHouseResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (array_key_exists('created_by', $this->getRecord()->getFillable())) {
            $data['created_by'] = auth()->id();
            $data['updated_by'] = auth()->id();
        }

        return $data;
    }
}