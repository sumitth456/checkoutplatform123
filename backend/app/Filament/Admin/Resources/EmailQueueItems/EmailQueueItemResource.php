<?php

namespace App\Filament\Admin\Resources\EmailQueueItems;

use App\Filament\Admin\Resources\EmailQueueItems\Pages\ListEmailQueueItems;
use App\Filament\Admin\Resources\EmailQueueItems\Pages\ViewEmailQueueItem;
use App\Filament\Admin\Resources\EmailQueueItems\Schemas\EmailQueueItemInfolist;
use App\Filament\Admin\Resources\EmailQueueItems\Tables\QueueItemsTable;
use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Models\EmailQueueItem;
use App\Support\Admin\AdminAuthorization;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class EmailQueueItemResource extends Resource
{
    protected static ?string $model = EmailQueueItem::class;

    protected static ?string $navigationLabel = 'Email Queue';

    protected static ?string $modelLabel = 'Queue Item';

    protected static ?string $pluralModelLabel = 'Email Queue';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::ADMIN;

    protected static ?int $navigationSort = 45;

    protected static ?string $recordTitleAttribute = 'subject';

    public static function infolist(Schema $schema): Schema
    {
        return EmailQueueItemInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return QueueItemsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmailQueueItems::route('/'),
            'view' => ViewEmailQueueItem::route('/{record}'),
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

    public static function canViewAny(): bool
    {
        return AdminAuthorization::can(auth()->user(), 'email_logs.view');
    }

    public static function canView(Model $record): bool
    {
        return AdminAuthorization::can(auth()->user(), 'email_logs.view');
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with(['template', 'provider']);
    }
}
