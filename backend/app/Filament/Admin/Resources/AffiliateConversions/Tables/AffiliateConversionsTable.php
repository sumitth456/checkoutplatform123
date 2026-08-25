<?php

namespace App\Filament\Admin\Resources\AffiliateConversions\Tables;

use App\Modules\Affiliate\Enums\ConversionType;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AffiliateConversionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('affiliate.name')
                    ->label('Affiliate')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('conversion_type')
                    ->badge()
                    ->formatStateUsing(fn (ConversionType|string $state): string => $state instanceof ConversionType ? str($state->name)->headline() : $state)
                    ->sortable(),
                TextColumn::make('revenue')
                    ->money('USD')
                    ->sortable(),
                TextColumn::make('commission_amount')
                    ->label('Commission')
                    ->money('USD')
                    ->sortable(),
                TextColumn::make('order.order_number')
                    ->label('Order')
                    ->placeholder('—'),
                TextColumn::make('converted_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('converted_at', 'desc')
            ->filters([
                SelectFilter::make('conversion_type')
                    ->options(collect(ConversionType::cases())->mapWithKeys(
                        fn (ConversionType $type): array => [$type->value => str($type->name)->headline()]
                    )->all()),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
