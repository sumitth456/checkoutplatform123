<?php

namespace App\Filament\Admin\Resources\AchProviders;

use App\Filament\Admin\Resources\AchProviders\Pages\CreateAchProvider;
use App\Filament\Admin\Resources\AchProviders\Pages\EditAchProvider;
use App\Filament\Admin\Resources\AchProviders\Pages\ListAchProviders;
use App\Filament\Admin\Resources\AchProviders\Pages\ViewAchProvider;
use App\Filament\Admin\Resources\AchProviders\Schemas\AchProviderForm;
use App\Filament\Admin\Resources\AchProviders\Schemas\AchProviderInfolist;
use App\Filament\Admin\Resources\AchProviders\Tables\AchProvidersTable;
use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Models\AchProvider;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AchProviderResource extends Resource
{
    protected static ?string $model = AchProvider::class;

    protected static ?string $navigationLabel = 'ACH Providers';

    protected static ?string $modelLabel = 'ACH Provider';

    protected static ?string $pluralModelLabel = 'ACH Providers';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::GATEWAYS;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return AchProviderForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AchProviderInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AchProvidersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAchProviders::route('/'),
            'create' => CreateAchProvider::route('/create'),
            'view' => ViewAchProvider::route('/{record}'),
            'edit' => EditAchProvider::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'slug', 'provider'];
    }
}
