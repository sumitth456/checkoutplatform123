<?php

namespace App\Filament\Admin\Widgets\CallCenter;

use App\Filament\Admin\Resources\CallCenterAgents\CallCenterAgentResource;
use App\Models\CallCenterAgent;
use App\Modules\CallCenter\Enums\AgentStatus;
use App\Support\Admin\AdminAuthorization;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class AgentStatusWidget extends TableWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Agent status';

    protected ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        return AdminAuthorization::can(auth()->user(), 'call_center.view');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                CallCenterAgent::query()
                    ->with(['callCenter'])
                    ->orderBy('name'),
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->url(fn (CallCenterAgent $record): string => CallCenterAgentResource::getUrl('view', ['record' => $record])),
                Tables\Columns\TextColumn::make('callCenter.name')
                    ->label('Call center'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (AgentStatus|string $state): string => match ($state instanceof AgentStatus ? $state : AgentStatus::tryFrom((string) $state)) {
                        AgentStatus::Active => 'success',
                        AgentStatus::OnCall => 'info',
                        AgentStatus::Break => 'warning',
                        AgentStatus::Offline => 'gray',
                        AgentStatus::Inactive => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (AgentStatus|string $state): string => $state instanceof AgentStatus ? str($state->name)->headline() : $state),
                Tables\Columns\IconColumn::make('is_logged_in')
                    ->label('Online')
                    ->boolean(),
                Tables\Columns\TextColumn::make('last_login_at')
                    ->label('Last login')
                    ->since()
                    ->placeholder('—'),
            ])
            ->paginated([10, 25, 50]);
    }
}
