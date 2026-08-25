<?php

namespace App\Filament\Admin\Resources\EmailAutomations;

use App\Filament\Admin\Resources\EmailAutomations\Pages\CreateEmailAutomation;
use App\Filament\Admin\Resources\EmailAutomations\Pages\EditEmailAutomation;
use App\Filament\Admin\Resources\EmailAutomations\Pages\ListEmailAutomations;
use App\Filament\Admin\Resources\EmailAutomations\Schemas\EmailAutomationForm;
use App\Filament\Admin\Resources\EmailAutomations\Tables\AutomationsTable;
use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Models\EmailAutomation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class EmailAutomationResource extends Resource
{
    protected static ?string $model = EmailAutomation::class;

    protected static ?string $navigationLabel = 'Email Automations';

    protected static ?string $modelLabel = 'Email Automation';

    protected static ?string $pluralModelLabel = 'Email Automations';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::ADMIN;

    protected static ?int $navigationSort = 44;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return EmailAutomationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AutomationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmailAutomations::route('/'),
            'create' => CreateEmailAutomation::route('/create'),
            'edit' => EditEmailAutomation::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'trigger_event'];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with(['template']);
    }
}
