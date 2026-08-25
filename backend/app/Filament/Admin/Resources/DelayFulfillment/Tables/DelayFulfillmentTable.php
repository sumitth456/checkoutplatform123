<?php

namespace App\Filament\Admin\Resources\DelayFulfillment\Tables;

use App\Filament\Admin\Resources\Orders\OrderResource;
use App\Modules\Order\Enums\FulfillmentRecordStatus;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DelayFulfillmentTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order.order_number')
                    ->label('Order')
                    ->searchable()
                    ->sortable()
                    ->url(fn ($record): ?string => $record->order
                        ? OrderResource::getUrl('view', ['record' => $record->order])
                        : null),
                TextColumn::make('order.customer_email')
                    ->label('Customer')
                    ->searchable()
                    ->formatStateUsing(fn ($state, $record): string => $state
                        ?: ($record->order?->customer?->name ?? '—')),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (FulfillmentRecordStatus|string $state): string => $state instanceof FulfillmentRecordStatus
                        ? str($state->name)->headline()
                        : str($state)->headline())
                    ->sortable(),
                TextColumn::make('warehouse_reference')
                    ->label('Warehouse')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('carrier')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('tracking_number')
                    ->label('Tracking')
                    ->searchable()
                    ->copyable()
                    ->placeholder('—'),
                TextColumn::make('scheduled_ship_date')
                    ->label('Ship date')
                    ->date()
                    ->sortable(),
            ])
            ->defaultSort('scheduled_ship_date', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(FulfillmentRecordStatus::cases())->mapWithKeys(
                        fn (FulfillmentRecordStatus $status): array => [$status->value => str($status->name)->headline()]
                    )->all()),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
