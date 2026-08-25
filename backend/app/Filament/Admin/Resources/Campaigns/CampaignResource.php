<?php

namespace App\Filament\Admin\Resources\Campaigns;

use App\Filament\Admin\Pages\CampaignManagement\ManageCampaignProducts;
use App\Filament\Admin\Resources\Campaigns\Pages\CreateCampaign;
use App\Filament\Admin\Resources\Campaigns\Pages\EditCampaign;
use App\Filament\Admin\Resources\Campaigns\Pages\ListCampaigns;
use App\Filament\Admin\Resources\Campaigns\Pages\ViewCampaign;
use App\Filament\Admin\Resources\Campaigns\RelationManagers\ActivityTimelineRelationManager;
use App\Filament\Admin\Resources\Campaigns\RelationManagers\CampaignDownsellsRelationManager;
use App\Filament\Admin\Resources\Campaigns\RelationManagers\CampaignFunnelMappingsRelationManager;
use App\Filament\Admin\Resources\Campaigns\RelationManagers\CampaignOrderBumpsRelationManager;
use App\Filament\Admin\Resources\Campaigns\RelationManagers\CampaignProductsRelationManager;
use App\Filament\Admin\Resources\Campaigns\RelationManagers\CampaignUpsellsRelationManager;
use App\Filament\Admin\Resources\Campaigns\Schemas\CampaignForm;
use App\Filament\Admin\Resources\Campaigns\Schemas\CampaignInfolist;
use App\Filament\Admin\Resources\Campaigns\Tables\CampaignsTable;
use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Models\Campaign;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class CampaignResource extends Resource
{
    protected static ?string $model = Campaign::class;

    protected static ?string $navigationLabel = 'Campaigns';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::CRM;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return CampaignForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CampaignInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CampaignsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            CampaignProductsRelationManager::class,
            CampaignFunnelMappingsRelationManager::class,
            CampaignUpsellsRelationManager::class,
            CampaignDownsellsRelationManager::class,
            CampaignOrderBumpsRelationManager::class,
            ActivityTimelineRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCampaigns::route('/'),
            'create' => CreateCampaign::route('/create'),
            'view' => ViewCampaign::route('/{record}'),
            'edit' => EditCampaign::route('/{record}/edit'),
            'products' => ManageCampaignProducts::route('/{record}/products'),
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
        return ['name', 'public_id', 'store_name'];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['brand']);
    }
}
