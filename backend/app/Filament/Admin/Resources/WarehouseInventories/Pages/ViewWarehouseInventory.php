<?php

namespace App\Filament\Admin\Resources\WarehouseInventories\Pages;

use App\Filament\Admin\Resources\WarehouseInventories\WarehouseInventoryResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewWarehouseInventory extends ViewRecord
{
    protected static string $resource = WarehouseInventoryResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }
}