<?php

namespace App\Filament\Admin\Resources\CallCenterAgentGroups\Schemas;

use App\Filament\Admin\Support\Infolists\AdminInfolistComponents;
use App\Models\Campaign;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class CallCenterAgentGroupInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                AdminInfolistComponents::summaryHeader(),
                AdminInfolistComponents::activityTimeline('Agent group timeline'),
                AdminInfolistComponents::detailsSection('Group details', [
                    Grid::make(3)->schema([
                        TextEntry::make('uuid')->label('UUID')->copyable(),
                        TextEntry::make('name'),
                        TextEntry::make('callCenter.name')->label('Call center'),
                        TextEntry::make('agents_count')->counts('agents')->label('Agents'),
                        TextEntry::make('created_at')->dateTime(),
                        TextEntry::make('updated_at')->dateTime(),
                        TextEntry::make('description')->columnSpanFull()->placeholder('—'),
                        TextEntry::make('assigned_campaign_ids')
                            ->label('Assigned campaigns')
                            ->formatStateUsing(fn (?array $state): string => self::resolveCampaignNames($state))
                            ->columnSpanFull(),
                        TextEntry::make('agents.name')
                            ->label('Agents')
                            ->badge()
                            ->columnSpanFull()
                            ->placeholder('—'),
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
