<?php

namespace App\Filament\Admin\Resources\LandingPages;

use App\Filament\Admin\Resources\LandingPages\Pages\BuildLandingPage;
use App\Filament\Admin\Resources\LandingPages\Pages\CreateLandingPage;
use App\Filament\Admin\Resources\LandingPages\Pages\DesignLandingPageCheckoutForm;
use App\Filament\Admin\Resources\LandingPages\Pages\EditLandingPage;
use App\Filament\Admin\Resources\LandingPages\Pages\ListLandingPages;
use App\Filament\Admin\Resources\LandingPages\Pages\ViewLandingPage;
use App\Filament\Admin\Resources\LandingPages\RelationManagers\VersionsRelationManager;
use App\Filament\Admin\Resources\LandingPages\Schemas\LandingPageForm;
use App\Filament\Admin\Resources\LandingPages\Schemas\LandingPageInfolist;
use App\Filament\Admin\Resources\LandingPages\Tables\LandingPagesTable;
use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Filament\Admin\Support\AdminNavigationParent;
use App\Models\LandingPage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class LandingPageResource extends Resource
{
    protected static ?string $model = LandingPage::class;

    protected static ?string $navigationLabel = 'Landing Pages';

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::ADMIN;

    protected static ?int $navigationSort = 10;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return LandingPageForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return LandingPageInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LandingPagesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            VersionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLandingPages::route('/'),
            'create' => CreateLandingPage::route('/create'),
            'view' => ViewLandingPage::route('/{record}'),
            'edit' => EditLandingPage::route('/{record}/edit'),
            'builder' => BuildLandingPage::route('/{record}/builder'),
            'checkout-form' => DesignLandingPageCheckoutForm::route('/{record}/checkout-form'),
        ];
    }
}
