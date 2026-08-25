<?php

namespace App\Filament\Admin\Resources\Warehouses\Pages;

use App\Filament\Admin\Resources\Warehouses\WarehouseResource;
use Filament\Resources\Pages\CreateRecord;

class CreateWarehouse extends CreateRecord
{
    protected static string $resource = WarehouseResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (array_key_exists('created_by', $this->getRecord()->getFillable())) {
            $data['created_by'] = auth()->id();
            $data['updated_by'] = auth()->id();
        }

        return $data;
    }
}