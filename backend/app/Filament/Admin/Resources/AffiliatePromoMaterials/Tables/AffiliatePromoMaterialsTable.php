<?php

namespace App\Filament\Admin\Resources\AffiliatePromoMaterials\Tables;

use App\Modules\Affiliate\Enums\PromoMaterialType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class AffiliatePromoMaterialsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (PromoMaterialType|string $state): string => $state instanceof PromoMaterialType ? str($state->name)->headline() : $state)
                    ->sortable(),
                TextColumn::make('offer.name')
                    ->label('Offer')
                    ->placeholder('—'),
                TextColumn::make('download_count')
                    ->label('Downloads')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('type')
                    ->options(collect(PromoMaterialType::cases())->mapWithKeys(
                        fn (PromoMaterialType $type): array => [$type->value => str($type->name)->headline()]
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
