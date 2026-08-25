<?php

namespace App\Filament\Admin\Exports;

use App\Models\Coupon;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class CouponExporter extends Exporter
{
    protected static ?string $model = Coupon::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('code'),
            ExportColumn::make('name'),
            ExportColumn::make('type'),
            ExportColumn::make('value')
                ->formatStateUsing(fn ($state) => Number::format((float) $state, 2)),
            ExportColumn::make('currency'),
            ExportColumn::make('minimum_order_amount'),
            ExportColumn::make('maximum_discount_amount'),
            ExportColumn::make('max_uses'),
            ExportColumn::make('used_count'),
            ExportColumn::make('is_active')
                ->formatStateUsing(fn ($state) => $state ? 'yes' : 'no'),
            ExportColumn::make('starts_at'),
            ExportColumn::make('expires_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $count = Number::format($export->successful_rows);

        return "Exported {$count} coupons.";
    }
}
