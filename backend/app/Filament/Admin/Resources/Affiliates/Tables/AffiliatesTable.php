<?php

namespace App\Filament\Admin\Resources\Affiliates\Tables;

use App\Modules\Affiliate\Enums\AffiliateStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class AffiliatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('username')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (AffiliateStatus|string $state): string => $state instanceof AffiliateStatus ? str($state->name)->headline() : $state)
                    ->sortable(),
                TextColumn::make('tracking_id')
                    ->label('Tracking ID')
                    ->copyable()
                    ->toggleable(),
                TextColumn::make('total_conversions')
                    ->label('Conversions')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('lifetime_revenue')
                    ->label('Revenue')
                    ->money('USD')
                    ->sortable(),
                TextColumn::make('pending_commission')
                    ->label('Pending')
                    ->money('USD')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('status')
                    ->options(collect(AffiliateStatus::cases())->mapWithKeys(
                        fn (AffiliateStatus $status): array => [$status->value => str($status->name)->headline()]
                    )->all()),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
