<?php

namespace App\Filament\Admin\Resources\AffiliateCommissions\Tables;

use App\Modules\Affiliate\Enums\CommissionStatus;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AffiliateCommissionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('affiliate.name')
                    ->label('Affiliate')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (CommissionStatus|string $state): string => $state instanceof CommissionStatus ? str($state->name)->headline() : $state)
                    ->sortable(),
                TextColumn::make('commission_type')
                    ->badge(),
                TextColumn::make('order_amount')
                    ->money('USD')
                    ->sortable(),
                TextColumn::make('commission_amount')
                    ->money('USD')
                    ->sortable(),
                TextColumn::make('rule_source')
                    ->toggleable()
                    ->placeholder('—'),
                TextColumn::make('earned_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('earned_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(CommissionStatus::cases())->mapWithKeys(
                        fn (CommissionStatus $status): array => [$status->value => str($status->name)->headline()]
                    )->all()),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
