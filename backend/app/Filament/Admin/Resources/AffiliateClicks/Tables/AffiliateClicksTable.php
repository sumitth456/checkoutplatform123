<?php

namespace App\Filament\Admin\Resources\AffiliateClicks\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AffiliateClicksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('click_id')
                    ->label('Click ID')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('affiliate.name')
                    ->label('Affiliate')
                    ->sortable(),
                TextColumn::make('offer.name')
                    ->label('Offer')
                    ->placeholder('—'),
                TextColumn::make('country_code')
                    ->label('Country')
                    ->toggleable(),
                IconColumn::make('is_unique')
                    ->boolean()
                    ->label('Unique'),
                TextColumn::make('clicked_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('clicked_at', 'desc')
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
