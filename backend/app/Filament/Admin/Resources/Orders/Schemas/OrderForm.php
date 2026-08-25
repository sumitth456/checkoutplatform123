<?php

namespace App\Filament\Admin\Resources\Orders\Schemas;

use App\Models\Order;
use App\Modules\Order\Enums\OrderFraudStatus;
use App\Modules\Order\Enums\OrderFulfillmentStatus;
use App\Modules\Order\Enums\OrderPaymentStatus;
use App\Modules\Order\Enums\OrderStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Order management')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('order_number')
                                ->disabled(),
                            TextInput::make('customer_email')
                                ->email()
                                ->disabled(),
                            Select::make('status')
                                ->options(collect(OrderStatus::cases())->mapWithKeys(
                                    fn (OrderStatus $status): array => [$status->value => str($status->name)->headline()]
                                )->all())
                                ->required(),
                            Select::make('payment_status')
                                ->options(collect(OrderPaymentStatus::cases())->mapWithKeys(
                                    fn (OrderPaymentStatus $status): array => [$status->value => str($status->name)->headline()]
                                )->all())
                                ->required(),
                            Select::make('fulfillment_status')
                                ->options(collect(OrderFulfillmentStatus::cases())->mapWithKeys(
                                    fn (OrderFulfillmentStatus $status): array => [$status->value => str($status->name)->headline()]
                                )->all())
                                ->required(),
                            Select::make('fraud_status')
                                ->options(collect(OrderFraudStatus::cases())->mapWithKeys(
                                    fn (OrderFraudStatus $status): array => [$status->value => str($status->name)->headline()]
                                )->all())
                                ->required(),
                            TextInput::make('fraud_score')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(100),
                            Select::make('duplicate_of_order_id')
                                ->label('Duplicate of order')
                                ->relationship('duplicateOf', 'order_number')
                                ->searchable()
                                ->preload()
                                ->nullable(),
                        ]),
                        Textarea::make('internal_notes')
                            ->rows(4)
                            ->columnSpanFull(),
                        Textarea::make('notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
                Section::make('Totals')
                    ->schema([
                        TextInput::make('grand_total')
                            ->disabled()
                            ->prefix(fn (?Order $record) => $record?->currency ?? 'USD'),
                        TextInput::make('refunded_total')
                            ->disabled(),
                    ]),
            ]);
    }
}
