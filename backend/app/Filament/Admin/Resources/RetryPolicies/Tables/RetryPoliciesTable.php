<?php

namespace App\Filament\Admin\Resources\RetryPolicies\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RetryPoliciesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('max_attempts')
                    ->label('Max attempts'),
                TextColumn::make('retry_offsets')
                    ->label('Offsets (days)')
                    ->formatStateUsing(fn ($state): string => is_array($state)
                        ? implode(', ', $state)
                        : (string) $state),
                TextColumn::make('updated_at')->dateTime(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
