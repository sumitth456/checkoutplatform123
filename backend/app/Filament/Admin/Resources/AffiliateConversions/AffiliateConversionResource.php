<?php

namespace App\Filament\Admin\Resources\AffiliateConversions;

use App\Filament\Admin\Resources\AffiliateConversions\Pages\ListAffiliateConversions;
use App\Filament\Admin\Resources\AffiliateConversions\Pages\ViewAffiliateConversion;
use App\Filament\Admin\Resources\AffiliateConversions\Schemas\AffiliateConversionInfolist;
use App\Filament\Admin\Resources\AffiliateConversions\Tables\AffiliateConversionsTable;
use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Filament\Admin\Support\AdminNavigationParent;
use App\Models\AffiliateConversion;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class AffiliateConversionResource extends Resource
{
    protected static ?string $model = AffiliateConversion::class;

    protected static ?string $navigationLabel = 'Conversions';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::CRM;

    protected static ?string $navigationParentItem = AdminNavigationParent::AFFILIATES;

    protected static ?int $navigationSort = 17;

    protected static ?string $recordTitleAttribute = 'uuid';

    public static function infolist(Schema $schema): Schema
    {
        return AffiliateConversionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AffiliateConversionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAffiliateConversions::route('/'),
            'view' => ViewAffiliateConversion::route('/{record}'),
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
        return parent::getEloquentQuery()->with(['affiliate', 'offer', 'order', 'customer', 'click']);
    }
}
