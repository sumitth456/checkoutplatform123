<?php

namespace App\Filament\Admin\Resources\AffiliatePromoMaterials;

use App\Filament\Admin\Resources\AffiliatePromoMaterials\Pages\CreateAffiliatePromoMaterial;
use App\Filament\Admin\Resources\AffiliatePromoMaterials\Pages\EditAffiliatePromoMaterial;
use App\Filament\Admin\Resources\AffiliatePromoMaterials\Pages\ListAffiliatePromoMaterials;
use App\Filament\Admin\Resources\AffiliatePromoMaterials\Pages\ViewAffiliatePromoMaterial;
use App\Filament\Admin\Resources\AffiliatePromoMaterials\Schemas\AffiliatePromoMaterialForm;
use App\Filament\Admin\Resources\AffiliatePromoMaterials\Schemas\AffiliatePromoMaterialInfolist;
use App\Filament\Admin\Resources\AffiliatePromoMaterials\Tables\AffiliatePromoMaterialsTable;
use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Filament\Admin\Support\AdminNavigationParent;
use App\Models\AffiliatePromoMaterial;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class AffiliatePromoMaterialResource extends Resource
{
    protected static ?string $model = AffiliatePromoMaterial::class;

    protected static ?string $navigationLabel = 'Promo Materials';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::CRM;

    protected static ?string $navigationParentItem = AdminNavigationParent::AFFILIATES;

    protected static ?int $navigationSort = 19;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return AffiliatePromoMaterialForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AffiliatePromoMaterialInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AffiliatePromoMaterialsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAffiliatePromoMaterials::route('/'),
            'create' => CreateAffiliatePromoMaterial::route('/create'),
            'view' => ViewAffiliatePromoMaterial::route('/{record}'),
            'edit' => EditAffiliatePromoMaterial::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['offer', 'landingPage', 'coupon']);
    }
}
