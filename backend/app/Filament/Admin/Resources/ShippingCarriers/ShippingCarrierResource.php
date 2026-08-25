<?php

namespace App\Filament\Admin\Resources\ShippingCarriers;

use App\Filament\Admin\Resources\ShippingCarriers\Pages\CreateShippingCarrier;
use App\Filament\Admin\Resources\ShippingCarriers\Pages\EditShippingCarrier;
use App\Filament\Admin\Resources\ShippingCarriers\Pages\ListShippingCarriers;
use App\Filament\Admin\Resources\ShippingCarriers\Pages\ViewShippingCarrier;
use App\Filament\Admin\Resources\ShippingCarriers\Schemas\ShippingCarrierForm;
use App\Filament\Admin\Resources\ShippingCarriers\Schemas\ShippingCarrierInfolist;
use App\Filament\Admin\Resources\ShippingCarriers\Tables\ShippingCarriersTable;
use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Models\ShippingCarrier;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class ShippingCarrierResource extends Resource
{
    protected static ?string $model = ShippingCarrier::class;

    protected static ?string $slug = 'shipping-carriers';

    protected static ?string $navigationLabel = 'Shipping Carriers';

    protected static ?string $modelLabel = 'Shipping carrier';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::FULFILLMENT;

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ShippingCarrierForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ShippingCarrierInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ShippingCarriersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShippingCarriers::route('/'),
            'create' => CreateShippingCarrier::route('/create'),
            'view' => ViewShippingCarrier::route('/{record}'),
            'edit' => EditShippingCarrier::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'code', 'uuid'];
    }
}