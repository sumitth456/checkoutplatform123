<?php

namespace App\Filament\Admin\Resources\CallCenters;

use App\Filament\Admin\Resources\CallCenters\Pages\CreateCallCenter;
use App\Filament\Admin\Resources\CallCenters\Pages\EditCallCenter;
use App\Filament\Admin\Resources\CallCenters\Pages\ListCallCenters;
use App\Filament\Admin\Resources\CallCenters\Pages\ViewCallCenter;
use App\Filament\Admin\Resources\CallCenters\Schemas\CallCenterForm;
use App\Filament\Admin\Resources\CallCenters\Schemas\CallCenterInfolist;
use App\Filament\Admin\Resources\CallCenters\Tables\CallCentersTable;
use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Filament\Admin\Support\AdminNavigationParent;
use App\Models\CallCenter;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class CallCenterResource extends Resource
{
    protected static ?string $model = CallCenter::class;

    protected static ?string $navigationLabel = 'Call Centers';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::CRM;

    protected static ?string $navigationParentItem = AdminNavigationParent::CALL_CENTER;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return CallCenterForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CallCenterInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CallCentersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCallCenters::route('/'),
            'create' => CreateCallCenter::route('/create'),
            'view' => ViewCallCenter::route('/{record}'),
            'edit' => EditCallCenter::route('/{record}/edit'),
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
        return ['name', 'phone_number', 'uuid'];
    }
}
