<?php

namespace App\Filament\Admin\Resources\WarehouseInventories;

use App\Filament\Admin\Resources\WarehouseInventories\Pages\CreateWarehouseInventory;
use App\Filament\Admin\Resources\WarehouseInventories\Pages\EditWarehouseInventory;
use App\Filament\Admin\Resources\WarehouseInventories\Pages\ListWarehouseInventories;
use App\Filament\Admin\Resources\WarehouseInventories\Pages\ViewWarehouseInventory;
use App\Filament\Admin\Resources\WarehouseInventories\Schemas\WarehouseInventoryForm;
use App\Filament\Admin\Resources\WarehouseInventories\Schemas\WarehouseInventoryInfolist;
use App\Filament\Admin\Resources\WarehouseInventories\Tables\WarehouseInventoriesTable;
use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Models\WarehouseInventory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class WarehouseInventoryResource extends Resource
{
    protected static ?string $model = WarehouseInventory::class;

    protected static ?string $slug = 'warehouse-inventory';

    protected static ?string $navigationLabel = 'Inventory';

    protected static ?string $modelLabel = 'Inventory item';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::FULFILLMENT;

    protected static ?int $navigationSort = 7;

    protected static ?string $recordTitleAttribute = 'sku';

    public static function form(Schema $schema): Schema
    {
        return WarehouseInventoryForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return WarehouseInventoryInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WarehouseInventoriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWarehouseInventories::route('/'),
            'create' => CreateWarehouseInventory::route('/create'),
            'view' => ViewWarehouseInventory::route('/{record}'),
            'edit' => EditWarehouseInventory::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['sku'];
    }
}