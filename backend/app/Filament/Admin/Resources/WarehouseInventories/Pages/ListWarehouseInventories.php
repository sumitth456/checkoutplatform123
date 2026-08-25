<?php

namespace App\Filament\Admin\Resources\WarehouseInventories\Pages;

use App\Filament\Admin\Resources\WarehouseInventories\WarehouseInventoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWarehouseInventories extends ListRecords
{
    protected static string $resource = WarehouseInventoryResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}