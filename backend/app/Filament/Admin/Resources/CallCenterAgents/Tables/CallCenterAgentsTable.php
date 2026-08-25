<?php

namespace App\Filament\Admin\Resources\CallCenterAgents\Tables;

use App\Models\CallCenter;
use App\Modules\CallCenter\Enums\AgentStatus;
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
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CallCenterAgentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('username')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('email')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('callCenter.name')
                    ->label('Call center')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (AgentStatus|string $state): string => $state instanceof AgentStatus ? str($state->name)->headline() : $state)
                    ->sortable(),
                IconColumn::make('is_logged_in')
                    ->label('Online')
                    ->boolean(),
                TextColumn::make('last_login_at')
                    ->label('Last login')
                    ->since()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('status')
                    ->options(collect(AgentStatus::cases())->mapWithKeys(
                        fn (AgentStatus $status): array => [$status->value => str($status->name)->headline()]
                    )->all()),
                SelectFilter::make('call_center_id')
                    ->label('Call center')
                    ->options(fn (): array => CallCenter::query()->orderBy('name')->pluck('name', 'id')->all()),
                TernaryFilter::make('is_logged_in')
                    ->label('Logged in'),
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
