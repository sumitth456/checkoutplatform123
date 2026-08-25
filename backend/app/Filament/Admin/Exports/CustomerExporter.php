<?php

namespace App\Filament\Admin\Exports;

use App\Models\Customer;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class CustomerExporter extends Exporter
{
    protected static ?string $model = Customer::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('uuid'),
            ExportColumn::make('email'),
            ExportColumn::make('first_name'),
            ExportColumn::make('last_name'),
            ExportColumn::make('full_name')
                ->label('Full name'),
            ExportColumn::make('phone'),
            ExportColumn::make('company'),
            ExportColumn::make('status'),
            ExportColumn::make('currency'),
            ExportColumn::make('orders_count'),
            ExportColumn::make('total_spent')
                ->formatStateUsing(fn ($state) => Number::format((float) $state, 2)),
            ExportColumn::make('accepts_marketing')
                ->formatStateUsing(fn ($state) => $state ? 'yes' : 'no'),
            ExportColumn::make('is_tax_exempt')
                ->formatStateUsing(fn ($state) => $state ? 'yes' : 'no'),
            ExportColumn::make('first_order_at'),
            ExportColumn::make('last_order_at'),
            ExportColumn::make('created_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $count = Number::format($export->successful_rows);

        return "Exported {$count} customers.";
    }
}
