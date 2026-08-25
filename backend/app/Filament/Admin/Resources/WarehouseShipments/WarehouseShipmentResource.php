<?php

namespace App\Filament\Admin\Resources\WarehouseShipments;

use App\Filament\Admin\Resources\WarehouseShipments\Pages\ListWarehouseShipments;
use App\Filament\Admin\Resources\WarehouseShipments\Pages\ViewWarehouseShipment;
use App\Filament\Admin\Resources\WarehouseShipments\Schemas\WarehouseShipmentInfolist;
use App\Filament\Admin\Resources\WarehouseShipments\Tables\WarehouseShipmentsTable;
use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Models\Fulfillment;
use App\Modules\Order\Enums\FulfillmentRecordStatus;
use App\Support\Admin\AdminAuthorization;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class WarehouseShipmentResource extends Resource
{
    protected static ?string $model = Fulfillment::class;

    protected static ?string $slug = 'warehouse-shipments';

    protected static ?string $navigationLabel = 'Warehouse Shipments';

    protected static ?string $modelLabel = 'Warehouse shipment';

    protected static ?string $pluralModelLabel = 'Warehouse shipments';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::FULFILLMENT;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $recordTitleAttribute = 'warehouse_reference';

    public static function infolist(Schema $schema): Schema
    {
        return WarehouseShipmentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WarehouseShipmentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWarehouseShipments::route('/'),
            'view' => ViewWarehouseShipment::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canViewAny(): bool
    {
        return AdminAuthorization::can(auth()->user(), 'warehouse.view');
    }

    public static function canView(Model $record): bool
    {
        return AdminAuthorization::can(auth()->user(), 'warehouse.view');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['order'])
            ->where(function (Builder $query): void {
                $query->whereIn('status', [
                    FulfillmentRecordStatus::Submitted,
                    FulfillmentRecordStatus::Shipped,
                    FulfillmentRecordStatus::Delivered,
                ])->orWhere(function (Builder $inner): void {
                    $inner->where('status', FulfillmentRecordStatus::Failed)
                        ->whereNotNull('warehouse_request');
                });
            });
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['warehouse_reference', 'tracking_number', 'uuid'];
    }
}
