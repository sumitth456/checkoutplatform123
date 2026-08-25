<?php

namespace App\Filament\Admin\Resources\AffiliateCommissions;

use App\Filament\Admin\Resources\AffiliateCommissions\Pages\ListAffiliateCommissions;
use App\Filament\Admin\Resources\AffiliateCommissions\Pages\ViewAffiliateCommission;
use App\Filament\Admin\Resources\AffiliateCommissions\Schemas\AffiliateCommissionInfolist;
use App\Filament\Admin\Resources\AffiliateCommissions\Tables\AffiliateCommissionsTable;
use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Filament\Admin\Support\AdminNavigationParent;
use App\Models\AffiliateCommission;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class AffiliateCommissionResource extends Resource
{
    protected static ?string $model = AffiliateCommission::class;

    protected static ?string $navigationLabel = 'Commissions';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::CRM;

    protected static ?string $navigationParentItem = AdminNavigationParent::AFFILIATES;

    protected static ?int $navigationSort = 15;

    protected static ?string $recordTitleAttribute = 'uuid';

    public static function infolist(Schema $schema): Schema
    {
        return AffiliateCommissionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AffiliateCommissionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAffiliateCommissions::route('/'),
            'view' => ViewAffiliateCommission::route('/{record}'),
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
        return parent::getEloquentQuery()->with(['affiliate', 'conversion', 'order', 'payout']);
    }
}
