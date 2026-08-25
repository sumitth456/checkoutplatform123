<?php

namespace App\Filament\Admin\Resources\WarehouseInventories\Schemas;

use App\Filament\Admin\Support\Infolists\AdminInfolistComponents;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class WarehouseInventoryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            AdminInfolistComponents::summaryHeader(),
            AdminInfolistComponents::detailsSection('Inventory details', [
                Grid::make(3)->schema([
                    TextEntry::make('sku'),
                    TextEntry::make('warehouse.name')->label('Warehouse'),
                    TextEntry::make('product.name')->label('Product')->placeholder('—'),
                    TextEntry::make('quantity_on_hand')->label('On hand'),
                    TextEntry::make('quantity_reserved')->label('Reserved'),
                    TextEntry::make('reorder_level')->label('Reorder level'),
                    TextEntry::make('created_at')->dateTime(),
                    TextEntry::make('updated_at')->dateTime(),
                ]),
            ]),
        ]);
    }
}
