<?php

namespace App\Filament\Admin\Resources\WarehouseInventories\Pages;

use App\Filament\Admin\Resources\WarehouseInventories\WarehouseInventoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateWarehouseInventory extends CreateRecord
{
    protected static string $resource = WarehouseInventoryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (array_key_exists('created_by', $this->getRecord()->getFillable())) {
            $data['created_by'] = auth()->id();
            $data['updated_by'] = auth()->id();
        }

        return $data;
    }
}