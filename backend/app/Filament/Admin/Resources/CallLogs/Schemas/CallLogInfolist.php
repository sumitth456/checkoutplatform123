<?php

namespace App\Filament\Admin\Resources\CallLogs\Schemas;

use App\Filament\Admin\Support\Infolists\AdminInfolistComponents;
use App\Modules\CallCenter\Enums\CallStatus;
use App\Modules\CallCenter\Enums\CallType;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class CallLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                AdminInfolistComponents::summaryHeader(),
                AdminInfolistComponents::activityTimeline('Call log timeline'),
                AdminInfolistComponents::detailsSection('Call details', [
                    Grid::make(3)->schema([
                        TextEntry::make('call_id')->label('Call ID')->copyable(),
                        TextEntry::make('uuid')->label('UUID')->copyable(),
                        TextEntry::make('call_type')
                            ->badge()
                            ->formatStateUsing(fn (CallType|string $state): string => $state instanceof CallType ? str($state->name)->headline() : $state),
                        TextEntry::make('call_status')
                            ->badge()
                            ->formatStateUsing(fn (CallStatus|string $state): string => $state instanceof CallStatus ? str($state->name)->headline() : $state),
                        TextEntry::make('customer_name')->label('Customer')->placeholder('—'),
                        TextEntry::make('customer.email')->label('Customer email')->placeholder('—'),
                        TextEntry::make('phone_number')->label('Phone')->placeholder('—'),
                        TextEntry::make('agent.name')->label('Agent')->placeholder('—'),
                        TextEntry::make('callCenter.name')->label('Call center')->placeholder('—'),
                        TextEntry::make('campaign.name')->label('Campaign')->placeholder('—'),
                        TextEntry::make('disposition.name')->label('Disposition')->placeholder('—'),
                        TextEntry::make('duration_seconds')
                            ->label('Duration')
                            ->formatStateUsing(fn (?int $state, $record): string => $record instanceof \App\Models\CallLog ? $record->formattedDuration() : '—'),
                        TextEntry::make('started_at')->dateTime()->placeholder('—'),
                        TextEntry::make('ended_at')->dateTime()->placeholder('—'),
                        TextEntry::make('recording_url')
                            ->label('Recording')
                            ->url(fn (?string $state): ?string => filled($state) ? $state : null, shouldOpenInNewTab: true)
                            ->placeholder('—'),
                        TextEntry::make('notes')->columnSpanFull()->placeholder('—'),
                    ]),
                ]),
            ]);
    }
}
