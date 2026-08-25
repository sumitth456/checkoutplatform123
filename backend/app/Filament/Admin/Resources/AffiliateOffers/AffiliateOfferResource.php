<?php

namespace App\Filament\Admin\Resources\AffiliateOffers;

use App\Filament\Admin\Resources\AffiliateOffers\Pages\CreateAffiliateOffer;
use App\Filament\Admin\Resources\AffiliateOffers\Pages\EditAffiliateOffer;
use App\Filament\Admin\Resources\AffiliateOffers\Pages\ListAffiliateOffers;
use App\Filament\Admin\Resources\AffiliateOffers\Pages\ViewAffiliateOffer;
use App\Filament\Admin\Resources\AffiliateOffers\Schemas\AffiliateOfferForm;
use App\Filament\Admin\Resources\AffiliateOffers\Schemas\AffiliateOfferInfolist;
use App\Filament\Admin\Resources\AffiliateOffers\Tables\AffiliateOffersTable;
use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Filament\Admin\Support\AdminNavigationParent;
use App\Models\AffiliateOffer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class AffiliateOfferResource extends Resource
{
    protected static ?string $model = AffiliateOffer::class;

    protected static ?string $navigationLabel = 'Offers';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGift;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::CRM;

    protected static ?string $navigationParentItem = AdminNavigationParent::AFFILIATES;

    protected static ?int $navigationSort = 12;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return AffiliateOfferForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AffiliateOfferInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AffiliateOffersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAffiliateOffers::route('/'),
            'create' => CreateAffiliateOffer::route('/create'),
            'view' => ViewAffiliateOffer::route('/{record}'),
            'edit' => EditAffiliateOffer::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'uuid'];
    }
}
