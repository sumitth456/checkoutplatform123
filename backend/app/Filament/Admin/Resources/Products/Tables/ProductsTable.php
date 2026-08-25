<?php

namespace App\Filament\Admin\Resources\Products\Tables;

use App\Filament\Admin\Exports\ProductExporter;
use App\Modules\Product\Enums\ProductStatus;
use App\Modules\Product\Enums\ProductType;
use App\Support\Admin\AdminAuthorization;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sku')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->limit(40),
                TextColumn::make('category.name')
                    ->label('Category')
                    ->toggleable()
                    ->placeholder('—'),
                TextColumn::make('brand.name')
                    ->label('Brand')
                    ->toggleable()
                    ->placeholder('—'),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (ProductType|string $state): string => $state instanceof ProductType ? $state->value : $state)
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (ProductStatus|string $state): string => $state instanceof ProductStatus ? $state->value : $state)
                    ->sortable(),
                TextColumn::make('base_price')
                    ->label('Price')
                    ->money(fn ($record) => $record->currency ?? 'USD')
                    ->sortable(),
                TextColumn::make('variants_count')
                    ->label('Variants')
                    ->counts('variants')
                    ->sortable(),
                IconColumn::make('is_subscription')
                    ->label('Subscription')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('status')
                    ->options(collect(ProductStatus::cases())->mapWithKeys(
                        fn (ProductStatus $status): array => [$status->value => str($status->name)->headline()]
                    )->all()),
                SelectFilter::make('type')
                    ->options(collect(ProductType::cases())->mapWithKeys(
                        fn (ProductType $type): array => [$type->value => str($type->name)->headline()]
                    )->all()),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(ProductExporter::class)
                        ->authorize(fn (): bool => AdminAuthorization::can(auth()->user(), 'products.export')),
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
