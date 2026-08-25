<?php

namespace App\Filament\Admin\Resources\FulfillmentBatches;

use App\Filament\Admin\Resources\FulfillmentBatches\Pages\CreateFulfillmentBatch;
use App\Filament\Admin\Resources\FulfillmentBatches\Pages\EditFulfillmentBatch;
use App\Filament\Admin\Resources\FulfillmentBatches\Pages\ListFulfillmentBatches;
use App\Filament\Admin\Resources\FulfillmentBatches\Pages\ViewFulfillmentBatch;
use App\Filament\Admin\Resources\FulfillmentBatches\Schemas\FulfillmentBatchForm;
use App\Filament\Admin\Resources\FulfillmentBatches\Schemas\FulfillmentBatchInfolist;
use App\Filament\Admin\Resources\FulfillmentBatches\Tables\FulfillmentBatchesTable;
use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Models\FulfillmentBatch;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class FulfillmentBatchResource extends Resource
{
    protected static ?string $model = FulfillmentBatch::class;

    protected static ?string $slug = 'fulfillment-batches';

    protected static ?string $navigationLabel = 'Fulfillment Batches';

    protected static ?string $modelLabel = 'Fulfillment batch';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::FULFILLMENT;

    protected static ?int $navigationSort = 8;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return FulfillmentBatchForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return FulfillmentBatchInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FulfillmentBatchesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFulfillmentBatches::route('/'),
            'create' => CreateFulfillmentBatch::route('/create'),
            'view' => ViewFulfillmentBatch::route('/{record}'),
            'edit' => EditFulfillmentBatch::route('/{record}/edit'),
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
        return ['name', 'uuid'];
    }
}