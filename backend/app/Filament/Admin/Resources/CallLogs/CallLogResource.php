<?php

namespace App\Filament\Admin\Resources\CallLogs;

use App\Filament\Admin\Resources\CallLogs\Pages\ListCallLogs;
use App\Filament\Admin\Resources\CallLogs\Pages\ViewCallLog;
use App\Filament\Admin\Resources\CallLogs\Schemas\CallLogInfolist;
use App\Filament\Admin\Resources\CallLogs\Tables\CallLogsTable;
use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Filament\Admin\Support\AdminNavigationParent;
use App\Models\CallLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class CallLogResource extends Resource
{
    protected static ?string $model = CallLog::class;

    protected static ?string $navigationLabel = 'Call Logs';

    protected static ?string $modelLabel = 'Call log';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoneArrowDownLeft;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::CRM;

    protected static ?string $navigationParentItem = AdminNavigationParent::CALL_CENTER;

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'call_id';

    public static function infolist(Schema $schema): Schema
    {
        return CallLogInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CallLogsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCallLogs::route('/'),
            'view' => ViewCallLog::route('/{record}'),
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

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['call_id', 'customer_name', 'phone_number', 'uuid'];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['agent', 'callCenter', 'campaign', 'disposition', 'customer']);
    }
}
