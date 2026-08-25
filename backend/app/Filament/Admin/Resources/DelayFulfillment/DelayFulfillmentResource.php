<?php

namespace App\Filament\Admin\Resources\DelayFulfillment;

use App\Filament\Admin\Resources\DelayFulfillment\Pages\ListDelayFulfillment;
use App\Filament\Admin\Resources\DelayFulfillment\Pages\ViewDelayFulfillment;
use App\Filament\Admin\Resources\DelayFulfillment\RelationManagers\FulfillmentTimelineRelationManager;
use App\Filament\Admin\Resources\DelayFulfillment\Schemas\DelayFulfillmentInfolist;
use App\Filament\Admin\Resources\DelayFulfillment\Tables\DelayFulfillmentTable;
use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Models\Fulfillment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class DelayFulfillmentResource extends Resource
{
    protected static ?string $model = Fulfillment::class;

    protected static ?string $slug = 'delay-fulfillment';

    protected static ?string $navigationLabel = 'Search Fulfillments';

    protected static ?string $modelLabel = 'Fulfillment';

    protected static ?string $pluralModelLabel = 'Fulfillments';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::FULFILLMENT;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'uuid';

    public static function infolist(Schema $schema): Schema
    {
        return DelayFulfillmentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DelayFulfillmentTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            FulfillmentTimelineRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDelayFulfillment::route('/'),
            'view' => ViewDelayFulfillment::route('/{record}'),
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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['order.customer', 'timelineEvents']);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['uuid', 'tracking_number', 'warehouse_reference'];
    }
}
