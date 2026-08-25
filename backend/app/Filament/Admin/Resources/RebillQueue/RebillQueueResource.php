<?php

namespace App\Filament\Admin\Resources\RebillQueue;

use App\Filament\Admin\Resources\RebillQueue\Pages\ListRebillQueue;
use App\Filament\Admin\Resources\RebillQueue\Tables\RebillQueueTable;
use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Models\Subscription;
use App\Modules\Subscription\Enums\SubscriptionStatus;
use App\Support\Admin\AdminAuthorization;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class RebillQueueResource extends Resource
{
    protected static ?string $model = Subscription::class;

    protected static ?string $slug = 'rebill-queue';

    protected static ?string $navigationLabel = 'Rebill Queue';

    protected static ?string $modelLabel = 'Rebill queue item';

    protected static ?string $pluralModelLabel = 'Rebill queue';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::ADMIN;

    protected static ?int $navigationSort = 33;

    public static function table(Table $table): Table
    {
        return RebillQueueTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRebillQueue::route('/'),
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
        return AdminAuthorization::can(auth()->user(), 'rebill_queue.view');
    }

    public static function canView(Model $record): bool
    {
        return AdminAuthorization::can(auth()->user(), 'rebill_queue.view');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['customer', 'plan'])
            ->whereIn('status', [
                SubscriptionStatus::Trial,
                SubscriptionStatus::Active,
                SubscriptionStatus::PastDue,
                SubscriptionStatus::Paused,
            ]);
    }
}
