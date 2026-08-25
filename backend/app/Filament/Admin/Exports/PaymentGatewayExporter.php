<?php

namespace App\Filament\Admin\Exports;

use App\Models\PaymentGatewayConfig;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class PaymentGatewayExporter extends Exporter
{
    protected static ?string $model = PaymentGatewayConfig::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('name'),
            ExportColumn::make('slug'),
            ExportColumn::make('provider'),
            ExportColumn::make('is_active')
                ->formatStateUsing(fn ($state) => $state ? 'yes' : 'no'),
            ExportColumn::make('sort_order')
                ->label('Priority'),
            ExportColumn::make('weight'),
            ExportColumn::make('daily_limit_amount'),
            ExportColumn::make('monthly_limit_amount'),
            ExportColumn::make('webhook_status')
                ->formatStateUsing(fn ($state) => $state instanceof \BackedEnum ? $state->value : (string) $state),
            ExportColumn::make('test_mode')
                ->formatStateUsing(fn ($state) => $state ? 'yes' : 'no'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $count = Number::format($export->successful_rows);

        return "Exported {$count} payment gateways.";
    }
}
