<?php

namespace App\Filament\Admin\Resources\DelayFulfillment\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FulfillmentTimelineRelationManager extends RelationManager
{
    protected static string $relationship = 'timelineEvents';

    protected static ?string $title = 'Timeline events';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('occurred_at')
                    ->label('When')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('event_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => str($state)->headline()),
                TextColumn::make('title')
                    ->searchable(),
                TextColumn::make('status_label')
                    ->label('Status')
                    ->badge()
                    ->placeholder('—'),
                TextColumn::make('description')
                    ->limit(50)
                    ->toggleable(),
            ])
            ->defaultSort('occurred_at', 'desc');
    }
}
