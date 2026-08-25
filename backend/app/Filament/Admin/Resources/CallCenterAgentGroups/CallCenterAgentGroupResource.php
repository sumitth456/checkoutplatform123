<?php

namespace App\Filament\Admin\Resources\CallCenterAgentGroups;

use App\Filament\Admin\Resources\CallCenterAgentGroups\Pages\CreateCallCenterAgentGroup;
use App\Filament\Admin\Resources\CallCenterAgentGroups\Pages\EditCallCenterAgentGroup;
use App\Filament\Admin\Resources\CallCenterAgentGroups\Pages\ListCallCenterAgentGroups;
use App\Filament\Admin\Resources\CallCenterAgentGroups\Pages\ViewCallCenterAgentGroup;
use App\Filament\Admin\Resources\CallCenterAgentGroups\Schemas\CallCenterAgentGroupForm;
use App\Filament\Admin\Resources\CallCenterAgentGroups\Schemas\CallCenterAgentGroupInfolist;
use App\Filament\Admin\Resources\CallCenterAgentGroups\Tables\CallCenterAgentGroupsTable;
use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Filament\Admin\Support\AdminNavigationParent;
use App\Models\CallCenterAgentGroup;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class CallCenterAgentGroupResource extends Resource
{
    protected static ?string $model = CallCenterAgentGroup::class;

    protected static ?string $navigationLabel = 'Agent Groups';

    protected static ?string $modelLabel = 'Agent group';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::CRM;

    protected static ?string $navigationParentItem = AdminNavigationParent::CALL_CENTER;

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return CallCenterAgentGroupForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CallCenterAgentGroupInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CallCenterAgentGroupsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCallCenterAgentGroups::route('/'),
            'create' => CreateCallCenterAgentGroup::route('/create'),
            'view' => ViewCallCenterAgentGroup::route('/{record}'),
            'edit' => EditCallCenterAgentGroup::route('/{record}/edit'),
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
        return ['name', 'uuid'];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['callCenter', 'agents']);
    }
}
