<?php

namespace App\Filament\Admin\Resources\CallCenterAgents\Schemas;

use App\Filament\Admin\Support\Infolists\AdminInfolistComponents;
use App\Models\Campaign;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class CallCenterAgentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                AdminInfolistComponents::summaryHeader(),
                AdminInfolistComponents::activityTimeline('Agent timeline'),
                AdminInfolistComponents::detailsSection('Agent details', [
                    Grid::make(3)->schema([
                        TextEntry::make('uuid')->label('UUID')->copyable(),
                        TextEntry::make('name'),
                        TextEntry::make('username'),
                        TextEntry::make('email'),
                        TextEntry::make('phone')->placeholder('—'),
                        TextEntry::make('extension')->placeholder('—'),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('callCenter.name')->label('Call center'),
                        TextEntry::make('role_label')->label('Role label')->placeholder('—'),
                        IconEntry::make('is_logged_in')->label('Logged in')->boolean(),
                        TextEntry::make('last_login_at')->label('Last login')->dateTime()->placeholder('—'),
                        TextEntry::make('call_logs_count')->counts('callLogs')->label('Call logs'),
                        TextEntry::make('created_at')->dateTime(),
                        TextEntry::make('updated_at')->dateTime(),
                        TextEntry::make('assigned_campaign_ids')
                            ->label('Assigned campaigns')
                            ->formatStateUsing(fn (?array $state): string => self::resolveCampaignNames($state))
                            ->columnSpanFull(),
                        TextEntry::make('permissions')
                            ->formatStateUsing(fn (?array $state): string => blank($state) ? '—' : json_encode($state, JSON_PRETTY_PRINT))
                            ->columnSpanFull(),
                    ]),
                ]),
            ]);
    }

    private static function resolveCampaignNames(?array $state): string
    {
        if (blank($state)) {
            return '—';
        }

        return Campaign::query()->whereIn('id', $state)->orderBy('name')->pluck('name')->join(', ') ?: '—';
    }
}
