<?php

namespace App\Filament\Admin\Resources\Products\Schemas;

use App\Filament\Admin\Support\Infolists\AdminInfolistComponents;
use App\Models\Product;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class ProductInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                AdminInfolistComponents::summaryHeader(),
                AdminInfolistComponents::activityTimeline('Product timeline'),
                AdminInfolistComponents::detailsSection('Product details', [
                    Grid::make(3)->schema([
                        TextEntry::make('name'),
                        TextEntry::make('sku')->label('SKU')->placeholder('—'),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('type')->badge(),
                        TextEntry::make('currency'),
                        TextEntry::make('subscriptionPlan.name')->label('Subscription plan')->placeholder('—'),
                        TextEntry::make('short_description')->columnSpanFull()->placeholder('—'),
                        ImageEntry::make('images')
                            ->disk('public')
                            ->visibility('public')
                            ->columnSpanFull()
                            ->hidden(fn (Product $record): bool => blank($record->images)),
                        TextEntry::make('base_price')->money(fn (Product $record) => $record->currency),
                        TextEntry::make('compare_at_price')->money(fn (Product $record) => $record->currency)->placeholder('—'),
                        IconEntry::make('is_taxable')->boolean(),
                        IconEntry::make('requires_shipping')->boolean(),
                        IconEntry::make('is_subscription')->boolean(),
                        TextEntry::make('variants_count')
                            ->label('Variants')
                            ->state(fn (Product $record): int => $record->variants()->count()),
                        TextEntry::make('inventory_total')
                            ->label('Total inventory')
                            ->state(fn (Product $record): int => $record->totalInventoryQuantity()),
                        TextEntry::make('published_at')->dateTime()->placeholder('—'),
                        TextEntry::make('created_at')->dateTime(),
                        TextEntry::make('updated_at')->dateTime(),
                    ]),
                ]),
            ]);
    }
}
