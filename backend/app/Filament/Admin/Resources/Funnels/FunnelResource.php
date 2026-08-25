<?php

namespace App\Filament\Admin\Resources\Funnels;

use App\Filament\Admin\Resources\Funnels\Pages\CreateFunnel;
use App\Filament\Admin\Resources\Funnels\Pages\EditFunnel;
use App\Filament\Admin\Resources\Funnels\Pages\ListFunnels;
use App\Filament\Admin\Resources\Funnels\Pages\ViewFunnel;
use App\Filament\Admin\Resources\Funnels\RelationManagers\ActivityTimelineRelationManager;
use App\Filament\Admin\Resources\Funnels\RelationManagers\FunnelStepsRelationManager;
use App\Filament\Admin\Resources\Funnels\RelationManagers\OrdersRelationManager;
use App\Filament\Admin\Resources\Funnels\Schemas\FunnelForm;
use App\Filament\Admin\Resources\Funnels\Schemas\FunnelInfolist;
use App\Filament\Admin\Resources\Funnels\Tables\FunnelsTable;
use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Models\Funnel;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class FunnelResource extends Resource
{
    protected static ?string $model = Funnel::class;

    protected static ?string $navigationLabel = 'Funnels';

    protected static bool $shouldRegisterNavigation = false;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::ADMIN;

    protected static ?int $navigationSort = 21;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFunnel;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return FunnelForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return FunnelInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FunnelsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            FunnelStepsRelationManager::class,
            OrdersRelationManager::class,
            ActivityTimelineRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFunnels::route('/'),
            'create' => CreateFunnel::route('/create'),
            'view' => ViewFunnel::route('/{record}'),
            'edit' => EditFunnel::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
