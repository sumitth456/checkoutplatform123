<?php

namespace App\Filament\Admin\Resources\AffiliateCampaignMappings;

use App\Filament\Admin\Resources\AffiliateCampaignMappings\Pages\CreateAffiliateCampaignMapping;
use App\Filament\Admin\Resources\AffiliateCampaignMappings\Pages\EditAffiliateCampaignMapping;
use App\Filament\Admin\Resources\AffiliateCampaignMappings\Pages\ListAffiliateCampaignMappings;
use App\Filament\Admin\Resources\AffiliateCampaignMappings\Pages\ViewAffiliateCampaignMapping;
use App\Filament\Admin\Resources\AffiliateCampaignMappings\Schemas\AffiliateCampaignMappingForm;
use App\Filament\Admin\Resources\AffiliateCampaignMappings\Schemas\AffiliateCampaignMappingInfolist;
use App\Filament\Admin\Resources\AffiliateCampaignMappings\Tables\AffiliateCampaignMappingsTable;
use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Filament\Admin\Support\AdminNavigationParent;
use App\Models\AffiliateCampaignMapping;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AffiliateCampaignMappingResource extends Resource
{
    protected static ?string $model = AffiliateCampaignMapping::class;

    protected static ?string $navigationLabel = 'Campaign Mapping';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::CRM;

    protected static ?string $navigationParentItem = AdminNavigationParent::AFFILIATES;

    protected static ?int $navigationSort = 13;

    protected static ?string $recordTitleAttribute = 'uuid';

    public static function form(Schema $schema): Schema
    {
        return AffiliateCampaignMappingForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AffiliateCampaignMappingInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AffiliateCampaignMappingsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAffiliateCampaignMappings::route('/'),
            'create' => CreateAffiliateCampaignMapping::route('/create'),
            'view' => ViewAffiliateCampaignMapping::route('/{record}'),
            'edit' => EditAffiliateCampaignMapping::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with(['affiliate', 'platformCampaign', 'offer']);
    }
}
