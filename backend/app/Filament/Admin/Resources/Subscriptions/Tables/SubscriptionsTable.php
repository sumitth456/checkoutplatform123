<?php

namespace App\Filament\Admin\Resources\Subscriptions\Tables;

use App\Filament\Admin\Exports\SubscriptionExporter;
use App\Modules\Subscription\Enums\SubscriptionStatus;
use App\Support\Admin\AdminAuthorization;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SubscriptionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['customer', 'plan']))
            ->columns([
                TextColumn::make('order_number_display')
                    ->label('Subscription')
                    ->state(fn ($record) => substr($record->uuid, 0, 8))
                    ->description(fn ($record) => $record->customer?->email)
                    ->searchable(['uuid'])
                    ->sortable(['uuid']),
                TextColumn::make('customer.email')
                    ->label('Customer')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('plan.name')
                    ->label('Plan')
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('amount')
                    ->money(fn ($record) => $record->currency)
                    ->sortable(),
                TextColumn::make('current_cycle_step')
                    ->label('Step')
                    ->sortable(),
                TextColumn::make('retry_count')
                    ->sortable(),
                TextColumn::make('next_billing_at')
                    ->label('Next charge')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('payment_provider')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('next_billing_at', 'asc')
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('status')
                    ->options(collect(SubscriptionStatus::cases())->mapWithKeys(
                        fn (SubscriptionStatus $status): array => [$status->value => str($status->name)->headline()]
                    )->all()),
                SelectFilter::make('subscription_plan_id')
                    ->label('Plan')
                    ->relationship('plan', 'name'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(SubscriptionExporter::class)
                        ->authorize(fn (): bool => AdminAuthorization::can(auth()->user(), 'subscriptions.export')),
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
