<?php

namespace App\Filament\Admin\Resources\EmailTemplates;

use App\Filament\Admin\Resources\EmailTemplates\Pages\CreateEmailTemplate;
use App\Filament\Admin\Resources\EmailTemplates\Pages\EditEmailTemplate;
use App\Filament\Admin\Resources\EmailTemplates\Pages\ListEmailTemplates;
use App\Filament\Admin\Resources\EmailTemplates\Pages\ViewEmailTemplate;
use App\Filament\Admin\Resources\EmailTemplates\RelationManagers\VersionsRelationManager;
use App\Filament\Admin\Resources\EmailTemplates\Schemas\EmailTemplateForm;
use App\Filament\Admin\Resources\EmailTemplates\Schemas\EmailTemplateInfolist;
use App\Filament\Admin\Resources\EmailTemplates\Tables\TemplatesTable;
use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Models\EmailTemplate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class EmailTemplateResource extends Resource
{
    protected static ?string $model = EmailTemplate::class;

    protected static ?string $navigationLabel = 'Email Templates';

    protected static ?string $modelLabel = 'Email Template';

    protected static ?string $pluralModelLabel = 'Email Templates';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::ADMIN;

    protected static ?int $navigationSort = 42;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return EmailTemplateForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EmailTemplateInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TemplatesTable::configure($table);
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
            'index' => ListEmailTemplates::route('/'),
            'create' => CreateEmailTemplate::route('/create'),
            'view' => ViewEmailTemplate::route('/{record}'),
            'edit' => EditEmailTemplate::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'slug', 'subject'];
    }
}
