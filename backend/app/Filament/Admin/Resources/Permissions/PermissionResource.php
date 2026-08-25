<?php



namespace App\Filament\Admin\Resources\Permissions;



use App\Filament\Admin\Resources\Permissions\Pages\EditPermission;

use App\Filament\Admin\Resources\Permissions\Pages\ListPermissions;

use App\Filament\Admin\Resources\Permissions\Pages\ViewPermission;

use App\Filament\Admin\Resources\Permissions\Schemas\PermissionForm;

use App\Filament\Admin\Resources\Permissions\Schemas\PermissionInfolist;

use App\Filament\Admin\Resources\Permissions\Tables\PermissionsTable;

use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Filament\Admin\Support\AdminNavigationParent;
use App\Models\Permission;

use BackedEnum;

use Filament\Resources\Resource;

use Filament\Schemas\Schema;

use Filament\Support\Icons\Heroicon;

use Filament\Tables\Table;

use Illuminate\Database\Eloquent\Model;

use UnitEnum;



class PermissionResource extends Resource

{

    protected static ?string $model = Permission::class;



    protected static ?string $navigationLabel = 'Permissions';



    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;



    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::ADMIN;



    protected static ?string $navigationParentItem = AdminNavigationParent::USERS;



    protected static ?int $navigationSort = 52;



    protected static ?string $recordTitleAttribute = 'name';



    public static function form(Schema $schema): Schema

    {

        return PermissionForm::configure($schema);

    }



    public static function infolist(Schema $schema): Schema

    {

        return PermissionInfolist::configure($schema);

    }



    public static function table(Table $table): Table

    {

        return PermissionsTable::configure($table);

    }



    public static function getPages(): array

    {

        return [

            'index' => ListPermissions::route('/'),

            'view' => ViewPermission::route('/{record}'),

            'edit' => EditPermission::route('/{record}/edit'),

        ];

    }



    public static function canCreate(): bool

    {

        return false;

    }



    public static function canDelete(Model $record): bool

    {

        return false;

    }



    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->withCount('roles')
            ->orderBy('group')
            ->orderBy('name');
    }

}

