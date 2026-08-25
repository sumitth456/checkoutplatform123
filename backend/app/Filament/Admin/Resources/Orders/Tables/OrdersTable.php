<?php

namespace App\Filament\Admin\Resources\Orders\Tables;

use App\Models\Order;
use App\Modules\Order\Enums\OrderPaymentStatus;
use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Order\Enums\OrderType;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'customer',
                'paymentGateway',
                'sourceSubscription.product',
                'parentOrder',
            ]))
            ->columns([
                TextColumn::make('order_number')
                    ->label('Order #')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('order_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (OrderType $state): string => match ($state) {
                        OrderType::Initial => 'Initial',
                        OrderType::Rebill => 'Rebill',
                    })
                    ->color(fn (OrderType $state): string => match ($state) {
                        OrderType::Initial => 'info',
                        OrderType::Rebill => 'warning',
                    })
                    ->sortable(),
                TextColumn::make('customer.full_name')
                    ->label('Customer')
                    ->searchable(['first_name', 'last_name'])
                    ->placeholder('—'),
                TextColumn::make('customer_email')
                    ->label('Email')
                    ->searchable()
                    ->copyable()
                    ->toggleable(),
                TextColumn::make('sourceSubscription.uuid')
                    ->label('Subscription ID')
                    ->placeholder('—')
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('billing_cycle_step')
                    ->label('Billing cycle')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('rebill_number')
                    ->label('Rebill #')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('rebill_retry_count')
                    ->label('Retry')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('sourceSubscription.status')
                    ->label('Subscription status')
                    ->badge()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('metadata.product_name')
                    ->label('Product')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('payment_status')
                    ->badge()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('grand_total')
                    ->money(fn (Order $record) => $record->currency ?? 'USD')
                    ->sortable(),
                TextColumn::make('placed_at')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('—'),
            ])
            ->defaultSort('placed_at', 'desc')
            ->filters([
                SelectFilter::make('order_type')
                    ->label('Order type')
                    ->options([
                        OrderType::Initial->value => 'Initial orders',
                        OrderType::Rebill->value => 'Rebill orders',
                    ]),
                SelectFilter::make('status')
                    ->options(collect(OrderStatus::cases())->mapWithKeys(
                        fn (OrderStatus $status): array => [$status->value => str($status->name)->headline()]
                    )->all()),
                SelectFilter::make('payment_status')
                    ->options(collect(OrderPaymentStatus::cases())->mapWithKeys(
                        fn (OrderPaymentStatus $status): array => [$status->value => str($status->name)->headline()]
                    )->all()),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
