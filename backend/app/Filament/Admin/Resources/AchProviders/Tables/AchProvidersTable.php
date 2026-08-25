<?php

namespace App\Filament\Admin\Resources\AchProviders\Tables;

use App\Models\AchProvider;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AchProvidersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('provider')->label('Driver')->badge(),
                TextColumn::make('status')->badge(),
                IconColumn::make('sandbox_mode')->boolean()->label('Sandbox'),
                IconColumn::make('is_default')->boolean()->label('Default'),
                TextColumn::make('priority')->sortable(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->defaultSort('priority');
    }
}
