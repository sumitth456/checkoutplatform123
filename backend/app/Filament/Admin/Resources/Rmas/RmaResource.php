<?php

namespace App\Filament\Admin\Resources\Rmas;

use App\Filament\Admin\Resources\Rmas\Pages\CreateRma;
use App\Filament\Admin\Resources\Rmas\Pages\EditRma;
use App\Filament\Admin\Resources\Rmas\Pages\ListRmas;
use App\Filament\Admin\Resources\Rmas\Pages\ViewRma;
use App\Filament\Admin\Resources\Rmas\Schemas\RmaForm;
use App\Filament\Admin\Resources\Rmas\Schemas\RmaInfolist;
use App\Filament\Admin\Resources\Rmas\Tables\RmasTable;
use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Models\Rma;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class RmaResource extends Resource
{
    protected static ?string $model = Rma::class;

    protected static ?string $slug = 'rmas';

    protected static ?string $navigationLabel = 'RMAs';

    protected static ?string $modelLabel = 'RMA';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::FULFILLMENT;

    protected static ?int $navigationSort = 12;

    protected static ?string $recordTitleAttribute = 'uuid';

    public static function form(Schema $schema): Schema
    {
        return RmaForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return RmaInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RmasTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRmas::route('/'),
            'create' => CreateRma::route('/create'),
            'view' => ViewRma::route('/{record}'),
            'edit' => EditRma::route('/{record}/edit'),
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
        return ['uuid', 'reason'];
    }
}