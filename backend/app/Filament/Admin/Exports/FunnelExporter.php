<?php

namespace App\Filament\Admin\Exports;

use App\Models\Funnel;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class FunnelExporter extends Exporter
{
    protected static ?string $model = Funnel::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('name'),
            ExportColumn::make('slug'),
            ExportColumn::make('status')
                ->formatStateUsing(fn ($state) => $state instanceof \BackedEnum ? $state->value : (string) $state),
            ExportColumn::make('currency'),
            ExportColumn::make('entryProduct.name')
                ->label('Entry product'),
            ExportColumn::make('defaultPaymentGateway.name')
                ->label('Default gateway'),
            ExportColumn::make('published_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $count = Number::format($export->successful_rows);

        return "Exported {$count} funnels.";
    }
}
