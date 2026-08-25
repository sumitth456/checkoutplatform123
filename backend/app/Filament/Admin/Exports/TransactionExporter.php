<?php

namespace App\Filament\Admin\Exports;

use App\Models\Transaction;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class TransactionExporter extends Exporter
{
    protected static ?string $model = Transaction::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('uuid'),
            ExportColumn::make('type'),
            ExportColumn::make('status'),
            ExportColumn::make('amount')
                ->formatStateUsing(fn ($state) => Number::format((float) $state, 2)),
            ExportColumn::make('currency'),
            ExportColumn::make('fee_amount')
                ->formatStateUsing(fn ($state) => Number::format((float) $state, 2)),
            ExportColumn::make('net_amount')
                ->formatStateUsing(fn ($state) => $state !== null ? Number::format((float) $state, 2) : ''),
            ExportColumn::make('customer.email')
                ->label('Customer email'),
            ExportColumn::make('order.order_number')
                ->label('Order number'),
            ExportColumn::make('paymentGateway.name')
                ->label('Gateway'),
            ExportColumn::make('external_id')
                ->label('Gateway transaction ID'),
            ExportColumn::make('payment_method_brand'),
            ExportColumn::make('payment_method_last4'),
            ExportColumn::make('failure_code'),
            ExportColumn::make('processed_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $count = Number::format($export->successful_rows);

        return "Exported {$count} transactions.";
    }
}
