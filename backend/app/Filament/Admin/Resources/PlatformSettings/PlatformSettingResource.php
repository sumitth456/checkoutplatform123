<?php



namespace App\Filament\Admin\Resources\PlatformSettings;



use App\Filament\Admin\Resources\PlatformSettings\Pages\EditPlatformSetting;

use App\Filament\Admin\Resources\PlatformSettings\Schemas\PlatformSettingForm;

use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Models\PlatformSetting;

use BackedEnum;

use Filament\Resources\Resource;

use Filament\Schemas\Schema;

use Filament\Support\Icons\Heroicon;

use UnitEnum;



class PlatformSettingResource extends Resource

{

    protected static ?string $model = PlatformSetting::class;



    protected static ?string $slug = 'settings';



    protected static ?string $navigationLabel = 'Settings';



    protected static ?string $modelLabel = 'Platform settings';



    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;



    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::ADMIN;



    protected static ?int $navigationSort = 64;



    public static function form(Schema $schema): Schema

    {

        return PlatformSettingForm::configure($schema);

    }



    public static function getPages(): array

    {

        return [

            'edit' => EditPlatformSetting::route('/{record}/edit'),

        ];

    }



    public static function getNavigationUrl(): string

    {

        return static::getUrl('edit', ['record' => PlatformSetting::current()]);

    }



    public static function canCreate(): bool

    {

        return false;

    }



    public static function canDelete($record): bool

    {

        return false;

    }

}

