<?php

namespace App\Filament\Admin\Exports;

use App\Models\Order;
use App\Modules\Order\Enums\OrderType;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class OrderExporter extends Exporter
{
    protected static ?string $model = Order::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('order_number'),
            ExportColumn::make('order_type')
                ->formatStateUsing(fn ($state) => $state instanceof OrderType ? $state->value : (string) $state),
            ExportColumn::make('parentOrder.order_number')
                ->label('Parent order'),
            ExportColumn::make('sourceSubscription.uuid')
                ->label('Subscription ID'),
            ExportColumn::make('billing_cycle_step')
                ->label('Billing cycle'),
            ExportColumn::make('rebill_number'),
            ExportColumn::make('rebill_retry_count')
                ->label('Retry count'),
            ExportColumn::make('sourceSubscription.status')
                ->label('Subscription status')
                ->formatStateUsing(fn ($state) => $state instanceof \BackedEnum ? $state->value : (string) ($state ?? '')),
            ExportColumn::make('metadata.product_name')
                ->label('Product'),
            ExportColumn::make('customer.email')
                ->label('Customer email'),
            ExportColumn::make('status')
                ->formatStateUsing(fn ($state) => $state instanceof \BackedEnum ? $state->value : (string) $state),
            ExportColumn::make('payment_status')
                ->formatStateUsing(fn ($state) => $state instanceof \BackedEnum ? $state->value : (string) $state),
            ExportColumn::make('fraud_status')
                ->formatStateUsing(fn ($state) => $state instanceof \BackedEnum ? $state->value : (string) $state),
            ExportColumn::make('fulfillment_status')
                ->formatStateUsing(fn ($state) => $state instanceof \BackedEnum ? $state->value : (string) $state),
            ExportColumn::make('currency'),
            ExportColumn::make('grand_total')
                ->formatStateUsing(fn ($state) => Number::format((float) $state, 2)),
            ExportColumn::make('refunded_total')
                ->formatStateUsing(fn ($state) => Number::format((float) $state, 2)),
            ExportColumn::make('paymentGateway.name')
                ->label('Gateway'),
            ExportColumn::make('placed_at'),
            ExportColumn::make('paid_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $count = Number::format($export->successful_rows);

        return "Exported {$count} orders.";
    }
}
