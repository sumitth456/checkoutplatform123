<?php

namespace App\Filament\Admin\Resources\EmailProviders;

use App\Filament\Admin\Resources\EmailProviders\Pages\CreateEmailProvider;
use App\Filament\Admin\Resources\EmailProviders\Pages\EditEmailProvider;
use App\Filament\Admin\Resources\EmailProviders\Pages\ListEmailProviders;
use App\Filament\Admin\Resources\EmailProviders\Pages\ViewEmailProvider;
use App\Filament\Admin\Resources\EmailProviders\Schemas\EmailProviderForm;
use App\Filament\Admin\Resources\EmailProviders\Schemas\EmailProviderInfolist;
use App\Filament\Admin\Resources\EmailProviders\Tables\ProvidersTable;
use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Models\EmailProvider;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class EmailProviderResource extends Resource
{
    protected static ?string $model = EmailProvider::class;

    protected static ?string $navigationLabel = 'Email Providers';

    protected static ?string $modelLabel = 'Email Provider';

    protected static ?string $pluralModelLabel = 'Email Providers';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::ADMIN;

    protected static ?int $navigationSort = 41;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return EmailProviderForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EmailProviderInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProvidersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmailProviders::route('/'),
            'create' => CreateEmailProvider::route('/create'),
            'view' => ViewEmailProvider::route('/{record}'),
            'edit' => EditEmailProvider::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'from_email'];
    }
}
