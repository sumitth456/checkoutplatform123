<?php

namespace App\Filament\Admin\Resources\Affiliates;

use App\Filament\Admin\Resources\Affiliates\Pages\CreateAffiliate;
use App\Filament\Admin\Resources\Affiliates\Pages\EditAffiliate;
use App\Filament\Admin\Resources\Affiliates\Pages\ListAffiliates;
use App\Filament\Admin\Resources\Affiliates\Pages\ViewAffiliate;
use App\Filament\Admin\Resources\Affiliates\Schemas\AffiliateForm;
use App\Filament\Admin\Resources\Affiliates\Schemas\AffiliateInfolist;
use App\Filament\Admin\Resources\Affiliates\Tables\AffiliatesTable;
use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Models\Affiliate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class AffiliateResource extends Resource
{
    protected static ?string $model = Affiliate::class;

    protected static ?string $navigationLabel = 'Affiliates';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::CRM;

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return AffiliateForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AffiliateInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AffiliatesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAffiliates::route('/'),
            'create' => CreateAffiliate::route('/create'),
            'view' => ViewAffiliate::route('/{record}'),
            'edit' => EditAffiliate::route('/{record}/edit'),
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
        return ['name', 'email', 'username', 'tracking_id', 'uuid'];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount(['offers', 'clicks', 'conversions']);
    }
}
