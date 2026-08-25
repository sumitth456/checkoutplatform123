<?php

namespace App\Filament\Admin\Exports;

use App\Models\Campaign;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class CampaignExporter extends Exporter
{
    protected static ?string $model = Campaign::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('public_id')->label('Campaign ID'),
            ExportColumn::make('uuid'),
            ExportColumn::make('name'),
            ExportColumn::make('status')
                ->formatStateUsing(fn ($state) => $state instanceof \BackedEnum ? $state->value : (string) $state),
            ExportColumn::make('brand.name')->label('Brand'),
            ExportColumn::make('store_name'),
            ExportColumn::make('store_code'),
            ExportColumn::make('currency'),
            ExportColumn::make('timezone'),
            ExportColumn::make('archived_at'),
            ExportColumn::make('created_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Exported '.Number::format($export->successful_rows).' campaigns.';
    }
}
