<?php

namespace App\Filament\Admin\Exports;

use App\Models\Fulfillment;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class WarehouseShipmentExporter extends Exporter
{
    protected static ?string $model = Fulfillment::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('uuid'),
            ExportColumn::make('order.order_number')
                ->label('Order number'),
            ExportColumn::make('status')
                ->formatStateUsing(fn ($state) => $state instanceof \BackedEnum ? $state->value : (string) $state),
            ExportColumn::make('warehouse_reference'),
            ExportColumn::make('scheduled_ship_date'),
            ExportColumn::make('shipped_at'),
            ExportColumn::make('tracking_number'),
            ExportColumn::make('carrier'),
            ExportColumn::make('shipment_status')
                ->formatStateUsing(fn ($state) => $state instanceof \BackedEnum ? $state->value : (string) $state),
            ExportColumn::make('delivery_status')
                ->formatStateUsing(fn ($state) => $state instanceof \BackedEnum ? $state->value : (string) $state),
            ExportColumn::make('failure_reason'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $count = Number::format($export->successful_rows);

        return "Exported {$count} warehouse shipments.";
    }
}
