<?php

namespace App\Filament\Admin\Exports;

use App\Models\Product;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class ProductExporter extends Exporter
{
    protected static ?string $model = Product::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('uuid'),
            ExportColumn::make('sku'),
            ExportColumn::make('name'),
            ExportColumn::make('slug'),
            ExportColumn::make('description')
                ->limit(500),
            ExportColumn::make('category.name')
                ->label('Category'),
            ExportColumn::make('brand.name')
                ->label('Brand'),
            ExportColumn::make('type')
                ->formatStateUsing(fn ($state) => $state instanceof \BackedEnum ? $state->value : (string) $state),
            ExportColumn::make('status')
                ->formatStateUsing(fn ($state) => $state instanceof \BackedEnum ? $state->value : (string) $state),
            ExportColumn::make('currency'),
            ExportColumn::make('base_price')
                ->formatStateUsing(fn ($state, Product $record) => Number::format((float) $state, 2)),
            ExportColumn::make('compare_at_price')
                ->formatStateUsing(fn ($state) => $state !== null ? Number::format((float) $state, 2) : ''),
            ExportColumn::make('cost')
                ->formatStateUsing(fn ($state) => $state !== null ? Number::format((float) $state, 2) : ''),
            ExportColumn::make('weight')
                ->formatStateUsing(fn ($state) => $state !== null ? (string) $state : ''),
            ExportColumn::make('is_subscription')
                ->formatStateUsing(fn ($state) => $state ? 'yes' : 'no'),
            ExportColumn::make('subscriptionPlan.name')
                ->label('Subscription plan'),
            ExportColumn::make('variants_count')
                ->counts('variants')
                ->label('Variants'),
            ExportColumn::make('published_at'),
            ExportColumn::make('created_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $count = Number::format($export->successful_rows);

        return "Exported {$count} products.";
    }
}
