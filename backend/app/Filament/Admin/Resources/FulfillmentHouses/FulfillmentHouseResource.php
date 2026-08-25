<?php

namespace App\Filament\Admin\Resources\FulfillmentHouses;

use App\Filament\Admin\Resources\FulfillmentHouses\Pages\CreateFulfillmentHouse;
use App\Filament\Admin\Resources\FulfillmentHouses\Pages\EditFulfillmentHouse;
use App\Filament\Admin\Resources\FulfillmentHouses\Pages\ListFulfillmentHouses;
use App\Filament\Admin\Resources\FulfillmentHouses\Pages\ViewFulfillmentHouse;
use App\Filament\Admin\Resources\FulfillmentHouses\Schemas\FulfillmentHouseForm;
use App\Filament\Admin\Resources\FulfillmentHouses\Schemas\FulfillmentHouseInfolist;
use App\Filament\Admin\Resources\FulfillmentHouses\Tables\FulfillmentHousesTable;
use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Models\FulfillmentHouse;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class FulfillmentHouseResource extends Resource
{
    protected static ?string $model = FulfillmentHouse::class;

    protected static ?string $slug = 'fulfillment-houses';

    protected static ?string $navigationLabel = 'Fulfillment Houses';

    protected static ?string $modelLabel = 'Fulfillment house';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::FULFILLMENT;

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return FulfillmentHouseForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return FulfillmentHouseInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FulfillmentHousesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFulfillmentHouses::route('/'),
            'create' => CreateFulfillmentHouse::route('/create'),
            'view' => ViewFulfillmentHouse::route('/{record}'),
            'edit' => EditFulfillmentHouse::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount('warehouses');
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'code', 'uuid'];
    }
}