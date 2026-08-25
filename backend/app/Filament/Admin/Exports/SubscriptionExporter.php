<?php

namespace App\Filament\Admin\Exports;

use App\Models\Subscription;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class SubscriptionExporter extends Exporter
{
    protected static ?string $model = Subscription::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('uuid'),
            ExportColumn::make('customer.email')
                ->label('Customer'),
            ExportColumn::make('plan.name')
                ->label('Plan'),
            ExportColumn::make('status')
                ->formatStateUsing(fn ($state) => $state instanceof \BackedEnum ? $state->value : (string) $state),
            ExportColumn::make('amount')
                ->formatStateUsing(fn ($state) => Number::format((float) $state, 2)),
            ExportColumn::make('currency'),
            ExportColumn::make('current_cycle_step'),
            ExportColumn::make('retry_count'),
            ExportColumn::make('next_billing_at'),
            ExportColumn::make('payment_provider'),
            ExportColumn::make('started_at'),
            ExportColumn::make('cancelled_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $count = Number::format($export->successful_rows);

        return "Exported {$count} subscriptions.";
    }
}
