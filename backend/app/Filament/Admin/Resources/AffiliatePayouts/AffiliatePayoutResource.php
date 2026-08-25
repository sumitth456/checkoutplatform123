<?php

namespace App\Filament\Admin\Resources\AffiliatePayouts;

use App\Filament\Admin\Resources\AffiliatePayouts\Pages\CreateAffiliatePayout;
use App\Filament\Admin\Resources\AffiliatePayouts\Pages\EditAffiliatePayout;
use App\Filament\Admin\Resources\AffiliatePayouts\Pages\ListAffiliatePayouts;
use App\Filament\Admin\Resources\AffiliatePayouts\Pages\ViewAffiliatePayout;
use App\Filament\Admin\Resources\AffiliatePayouts\Schemas\AffiliatePayoutForm;
use App\Filament\Admin\Resources\AffiliatePayouts\Schemas\AffiliatePayoutInfolist;
use App\Filament\Admin\Resources\AffiliatePayouts\Tables\AffiliatePayoutsTable;
use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Filament\Admin\Support\AdminNavigationParent;
use App\Models\AffiliatePayout;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AffiliatePayoutResource extends Resource
{
    protected static ?string $model = AffiliatePayout::class;

    protected static ?string $navigationLabel = 'Payouts';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::CRM;

    protected static ?string $navigationParentItem = AdminNavigationParent::AFFILIATES;

    protected static ?int $navigationSort = 18;

    protected static ?string $recordTitleAttribute = 'uuid';

    public static function form(Schema $schema): Schema
    {
        return AffiliatePayoutForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AffiliatePayoutInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AffiliatePayoutsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAffiliatePayouts::route('/'),
            'create' => CreateAffiliatePayout::route('/create'),
            'view' => ViewAffiliatePayout::route('/{record}'),
            'edit' => EditAffiliatePayout::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with(['affiliate', 'approver']);
    }
}
