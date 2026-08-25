<?php

namespace App\Filament\Admin\Resources\CallCenterAgents;

use App\Filament\Admin\Resources\CallCenterAgents\Pages\CreateCallCenterAgent;
use App\Filament\Admin\Resources\CallCenterAgents\Pages\EditCallCenterAgent;
use App\Filament\Admin\Resources\CallCenterAgents\Pages\ListCallCenterAgents;
use App\Filament\Admin\Resources\CallCenterAgents\Pages\ViewCallCenterAgent;
use App\Filament\Admin\Resources\CallCenterAgents\Schemas\CallCenterAgentForm;
use App\Filament\Admin\Resources\CallCenterAgents\Schemas\CallCenterAgentInfolist;
use App\Filament\Admin\Resources\CallCenterAgents\Tables\CallCenterAgentsTable;
use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Filament\Admin\Support\AdminNavigationParent;
use App\Models\CallCenterAgent;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class CallCenterAgentResource extends Resource
{
    protected static ?string $model = CallCenterAgent::class;

    protected static ?string $navigationLabel = 'Agents';

    protected static ?string $modelLabel = 'Agent';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::CRM;

    protected static ?string $navigationParentItem = AdminNavigationParent::CALL_CENTER;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return CallCenterAgentForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CallCenterAgentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CallCenterAgentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCallCenterAgents::route('/'),
            'create' => CreateCallCenterAgent::route('/create'),
            'view' => ViewCallCenterAgent::route('/{record}'),
            'edit' => EditCallCenterAgent::route('/{record}/edit'),
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
        return ['name', 'username', 'email', 'phone'];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['callCenter']);
    }
}
