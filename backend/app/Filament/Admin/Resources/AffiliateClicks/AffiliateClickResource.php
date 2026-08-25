<?php

namespace App\Filament\Admin\Resources\AffiliateClicks;

use App\Filament\Admin\Resources\AffiliateClicks\Pages\ListAffiliateClicks;
use App\Filament\Admin\Resources\AffiliateClicks\Pages\ViewAffiliateClick;
use App\Filament\Admin\Resources\AffiliateClicks\Schemas\AffiliateClickInfolist;
use App\Filament\Admin\Resources\AffiliateClicks\Tables\AffiliateClicksTable;
use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Filament\Admin\Support\AdminNavigationParent;
use App\Models\AffiliateClick;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class AffiliateClickResource extends Resource
{
    protected static ?string $model = AffiliateClick::class;

    protected static ?string $navigationLabel = 'Clicks';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCursorArrowRays;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::CRM;

    protected static ?string $navigationParentItem = AdminNavigationParent::AFFILIATES;

    protected static ?int $navigationSort = 16;

    protected static ?string $recordTitleAttribute = 'click_id';

    public static function infolist(Schema $schema): Schema
    {
        return AffiliateClickInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AffiliateClicksTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAffiliateClicks::route('/'),
            'view' => ViewAffiliateClick::route('/{record}'),
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
        return parent::getEloquentQuery()->with(['affiliate', 'offer', 'platformCampaign', 'trackingLink']);
    }
}
