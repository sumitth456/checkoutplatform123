<?php

namespace App\Filament\Admin\Resources\EmailAutomations\Tables;

use App\Support\Admin\AdminAuthorization;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class AutomationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('priority')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('trigger_event')
                    ->label('Trigger')
                    ->formatStateUsing(fn (string $state): string => str($state)->replace('_', ' ')->title())
                    ->badge()
                    ->sortable(),
                TextColumn::make('template.name')
                    ->label('Template')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('delay_minutes')
                    ->label('Delay (min)')
                    ->sortable(),
                TextColumn::make('priority')
                    ->sortable(),
                ToggleColumn::make('is_active')
                    ->label('Active')
                    ->disabled(fn (): bool => ! AdminAuthorization::can(auth()->user(), 'email_automations.manage')),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
