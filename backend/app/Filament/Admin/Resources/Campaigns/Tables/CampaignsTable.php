<?php

namespace App\Filament\Admin\Resources\Campaigns\Tables;

use App\Modules\Campaign\Enums\CampaignStatus;
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

class CampaignsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('public_id')
                    ->label('Campaign ID')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (CampaignStatus|string $state): string => $state instanceof CampaignStatus ? $state->value : $state)
                    ->sortable(),
                TextColumn::make('brand.name')
                    ->label('Brand')
                    ->toggleable(),
                TextColumn::make('store_name')
                    ->label('Store')
                    ->toggleable(),
                TextColumn::make('currency')
                    ->toggleable(),
                TextColumn::make('funnels_count')
                    ->counts('funnels')
                    ->label('Funnels'),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('status')
                    ->options(collect(CampaignStatus::cases())->mapWithKeys(
                        fn (CampaignStatus $status): array => [$status->value => str($status->name)->headline()]
                    )->all()),
                SelectFilter::make('brand_id')
                    ->label('Brand')
                    ->relationship('brand', 'name'),
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
