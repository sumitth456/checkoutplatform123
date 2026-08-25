<?php

namespace App\Filament\Admin\Resources\CallLogs\Tables;

use App\Filament\Admin\Resources\CallLogs\CallLogResource;
use App\Models\CallCenterAgent;
use App\Models\CallLog;
use App\Models\Campaign;
use App\Modules\CallCenter\Enums\CallStatus;
use App\Modules\CallCenter\Enums\CallType;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CallLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('call_id')
                    ->label('Call ID')
                    ->searchable()
                    ->sortable()
                    ->url(fn (CallLog $record): string => CallLogResource::getUrl('view', ['record' => $record])),
                TextColumn::make('customer_name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phone_number')
                    ->label('Phone')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('agent.name')
                    ->label('Agent')
                    ->sortable(),
                TextColumn::make('campaign.name')
                    ->label('Campaign')
                    ->toggleable(),
                TextColumn::make('call_type')
                    ->badge()
                    ->formatStateUsing(fn (CallType|string $state): string => $state instanceof CallType ? str($state->name)->headline() : $state)
                    ->sortable(),
                TextColumn::make('call_status')
                    ->badge()
                    ->formatStateUsing(fn (CallStatus|string $state): string => $state instanceof CallStatus ? str($state->name)->headline() : $state)
                    ->sortable(),
                TextColumn::make('duration_seconds')
                    ->label('Duration')
                    ->formatStateUsing(fn (?int $state, CallLog $record): string => $record->formattedDuration())
                    ->sortable(),
                TextColumn::make('disposition.name')
                    ->label('Disposition')
                    ->toggleable(),
                TextColumn::make('started_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('started_at', 'desc')
            ->filters([
                SelectFilter::make('call_type')
                    ->options(collect(CallType::cases())->mapWithKeys(
                        fn (CallType $type): array => [$type->value => str($type->name)->headline()]
                    )->all()),
                SelectFilter::make('call_status')
                    ->options(collect(CallStatus::cases())->mapWithKeys(
                        fn (CallStatus $status): array => [$status->value => str($status->name)->headline()]
                    )->all()),
                SelectFilter::make('agent_id')
                    ->label('Agent')
                    ->options(fn (): array => CallCenterAgent::query()->orderBy('name')->pluck('name', 'id')->all()),
                SelectFilter::make('platform_campaign_id')
                    ->label('Campaign')
                    ->options(fn (): array => Campaign::query()->orderBy('name')->pluck('name', 'id')->all()),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
