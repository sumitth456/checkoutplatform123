<?php

namespace App\Filament\Admin\Resources\AffiliateTrackingLinks;

use App\Filament\Admin\Resources\AffiliateTrackingLinks\Pages\CreateAffiliateTrackingLink;
use App\Filament\Admin\Resources\AffiliateTrackingLinks\Pages\EditAffiliateTrackingLink;
use App\Filament\Admin\Resources\AffiliateTrackingLinks\Pages\ListAffiliateTrackingLinks;
use App\Filament\Admin\Resources\AffiliateTrackingLinks\Pages\ViewAffiliateTrackingLink;
use App\Filament\Admin\Resources\AffiliateTrackingLinks\Schemas\AffiliateTrackingLinkForm;
use App\Filament\Admin\Resources\AffiliateTrackingLinks\Schemas\AffiliateTrackingLinkInfolist;
use App\Filament\Admin\Resources\AffiliateTrackingLinks\Tables\AffiliateTrackingLinksTable;
use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Filament\Admin\Support\AdminNavigationParent;
use App\Models\AffiliateTrackingLink;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class AffiliateTrackingLinkResource extends Resource
{
    protected static ?string $model = AffiliateTrackingLink::class;

    protected static ?string $navigationLabel = 'Tracking Links';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::CRM;

    protected static ?string $navigationParentItem = AdminNavigationParent::AFFILIATES;

    protected static ?int $navigationSort = 14;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return AffiliateTrackingLinkForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AffiliateTrackingLinkInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AffiliateTrackingLinksTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAffiliateTrackingLinks::route('/'),
            'create' => CreateAffiliateTrackingLink::route('/create'),
            'view' => ViewAffiliateTrackingLink::route('/{record}'),
            'edit' => EditAffiliateTrackingLink::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['affiliate', 'offer', 'platformCampaign', 'landingPage']);
    }
}
