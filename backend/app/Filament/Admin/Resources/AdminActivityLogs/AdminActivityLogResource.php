<?php



namespace App\Filament\Admin\Resources\AdminActivityLogs;



use App\Filament\Admin\Resources\AdminActivityLogs\Pages\ListAdminActivityLogs;

use App\Filament\Admin\Resources\AdminActivityLogs\Pages\ViewAdminActivityLog;

use App\Filament\Admin\Resources\AdminActivityLogs\Schemas\AdminActivityLogInfolist;

use App\Filament\Admin\Resources\AdminActivityLogs\Tables\AdminActivityLogsTable;

use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Models\AdminActivityLog;

use BackedEnum;

use Filament\Resources\Resource;

use Filament\Schemas\Schema;

use Filament\Support\Icons\Heroicon;

use Filament\Tables\Table;

use Illuminate\Database\Eloquent\Builder;

use Illuminate\Database\Eloquent\Model;

use UnitEnum;



class AdminActivityLogResource extends Resource

{

    protected static ?string $model = AdminActivityLog::class;



    protected static ?string $navigationLabel = 'Admin activity';



    protected static ?string $modelLabel = 'Admin activity';



    protected static ?string $pluralModelLabel = 'Admin activity logs';



    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;



    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::ADMIN;



    protected static ?int $navigationSort = 60;



    protected static ?string $recordTitleAttribute = 'uuid';



    public static function infolist(Schema $schema): Schema

    {

        return AdminActivityLogInfolist::configure($schema);

    }



    public static function table(Table $table): Table

    {

        return AdminActivityLogsTable::configure($table);

    }



    public static function getPages(): array

    {

        return [

            'index' => ListAdminActivityLogs::route('/'),

            'view' => ViewAdminActivityLog::route('/{record}'),

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



    public static function getGloballySearchableAttributes(): array

    {

        return ['uuid', 'event', 'description', 'module'];

    }



    public static function getEloquentQuery(): Builder

    {

        return parent::getEloquentQuery()->with(['causer']);

    }

}

