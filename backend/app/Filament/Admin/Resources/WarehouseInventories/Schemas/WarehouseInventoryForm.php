<?php

namespace App\Filament\Admin\Resources\WarehouseInventories\Schemas;

use App\Models\Product;
use App\Models\Warehouse;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WarehouseInventoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Section::make('Inventory')->columnSpanFull()->schema([
                Grid::make(2)->schema([
                    Select::make('warehouse_id')
                        ->label('Warehouse')
                        ->options(fn (): array => Warehouse::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->preload()
                        ->required(),
                    Select::make('product_id')
                        ->label('Product')
                        ->options(fn (): array => Product::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->preload(),
                    TextInput::make('sku')->required()->maxLength(100),
                    TextInput::make('quantity_on_hand')->numeric()->default(0)->required(),
                    TextInput::make('quantity_reserved')->numeric()->default(0),
                    TextInput::make('reorder_level')->numeric()->default(0),
                ]),
            ]),
        ]);
    }
}
