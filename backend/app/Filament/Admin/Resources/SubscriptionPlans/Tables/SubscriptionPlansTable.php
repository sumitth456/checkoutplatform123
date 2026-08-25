<?php

namespace App\Filament\Admin\Resources\SubscriptionPlans\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SubscriptionPlansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('slug'),
                TextColumn::make('currency'),
                IconColumn::make('is_active')->boolean(),
                TextColumn::make('retryPolicy.name')->label('Retry policy'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
