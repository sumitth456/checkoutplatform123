<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\Subscriptions\SubscriptionResource;
use App\Models\Subscription;
use App\Modules\Subscription\Enums\SubscriptionStatus;
use App\Support\Admin\AdminAuthorization;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class UpcomingRebillsWidget extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 1;

    protected static ?string $heading = 'Upcoming rebills';

    public static function canView(): bool
    {
        return AdminAuthorization::can(auth()->user(), 'rebill_queue.view');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Subscription::query()
                    ->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::PastDue, SubscriptionStatus::Trial])
                    ->whereNotNull('next_billing_at')
                    ->orderBy('next_billing_at')
                    ->limit(6)
            )
            ->columns([
                Tables\Columns\TextColumn::make('customer.email')
                    ->label('Customer')
                    ->limit(20),
                Tables\Columns\TextColumn::make('uuid')
                    ->label('Subscription')
                    ->limit(8)
                    ->url(fn (Subscription $record): string => SubscriptionResource::getUrl('view', ['record' => $record])),
                Tables\Columns\TextColumn::make('amount')->money(fn (Subscription $record) => $record->currency),
                Tables\Columns\TextColumn::make('next_billing_at')->dateTime('M j, H:i'),
            ])
            ->paginated(false);
    }
}
