<?php

namespace App\Filament\Admin\Resources\WarehouseSettings;

use App\Filament\Admin\Resources\WarehouseSettings\Pages\EditWarehouseSetting;
use App\Filament\Admin\Resources\WarehouseSettings\Schemas\WarehouseSettingForm;
use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Models\WarehouseSetting;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class WarehouseSettingResource extends Resource
{
    protected static ?string $model = WarehouseSetting::class;

    protected static ?string $slug = 'warehouse/integration';

    protected static ?string $navigationLabel = 'Warehouse Integration';

    protected static ?string $modelLabel = 'Warehouse integration';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServerStack;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::FULFILLMENT;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return WarehouseSettingForm::configure($schema);
    }

    public static function getPages(): array
    {
        return [
            'edit' => EditWarehouseSetting::route('/'),
        ];
    }

    public static function getNavigationUrl(): string
    {
        return static::getUrl('edit', ['record' => WarehouseSetting::current()]);
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
