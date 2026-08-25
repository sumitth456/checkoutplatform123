<?php

namespace App\Filament\Admin\Widgets\CallCenter;

use App\Filament\Admin\Resources\CallLogs\CallLogResource;
use App\Models\CallLog;
use App\Modules\CallCenter\Enums\CallStatus;
use App\Modules\CallCenter\Enums\CallType;
use App\Support\Admin\AdminAuthorization;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentCallLogsWidget extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Recent call logs';

    protected ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        return AdminAuthorization::can(auth()->user(), 'call_center.view');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                CallLog::query()
                    ->with(['agent', 'campaign', 'disposition'])
                    ->latest('started_at')
                    ->limit(10),
            )
            ->columns([
                Tables\Columns\TextColumn::make('call_id')
                    ->label('Call ID')
                    ->url(fn (CallLog $record): string => CallLogResource::getUrl('view', ['record' => $record])),
                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Customer')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('agent.name')
                    ->label('Agent')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('call_type')
                    ->badge()
                    ->formatStateUsing(fn (CallType|string $state): string => $state instanceof CallType ? str($state->name)->headline() : $state),
                Tables\Columns\TextColumn::make('call_status')
                    ->badge()
                    ->formatStateUsing(fn (CallStatus|string $state): string => $state instanceof CallStatus ? str($state->name)->headline() : $state),
                Tables\Columns\TextColumn::make('duration_seconds')
                    ->label('Duration')
                    ->formatStateUsing(fn (?int $state, CallLog $record): string => $record->formattedDuration()),
                Tables\Columns\TextColumn::make('started_at')
                    ->since(),
            ])
            ->paginated(false);
    }
}
