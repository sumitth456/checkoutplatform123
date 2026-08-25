<?php

namespace App\Filament\Admin\Resources\CallCenters\Schemas;

use App\Filament\Admin\Support\Infolists\AdminInfolistComponents;
use App\Models\Campaign;
use App\Models\Role;
use App\Models\User;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class CallCenterInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                AdminInfolistComponents::summaryHeader(),
                AdminInfolistComponents::activityTimeline('Call center timeline'),
                AdminInfolistComponents::detailsSection('Call center details', [
                    Grid::make(3)->schema([
                        TextEntry::make('uuid')->label('UUID')->copyable(),
                        TextEntry::make('name'),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('timezone'),
                        TextEntry::make('default_language')->label('Default language')->placeholder('—'),
                        TextEntry::make('phone_number')->label('Phone')->placeholder('—'),
                        TextEntry::make('extension')->placeholder('—'),
                        TextEntry::make('country_code')->label('Country code')->placeholder('—'),
                        TextEntry::make('agents_count')->counts('agents')->label('Agents'),
                        TextEntry::make('call_logs_count')->counts('callLogs')->label('Call logs'),
                        TextEntry::make('created_at')->dateTime(),
                        TextEntry::make('updated_at')->dateTime(),
                        TextEntry::make('description')->columnSpanFull()->placeholder('—'),
                        TextEntry::make('allowed_campaign_ids')
                            ->label('Allowed campaigns')
                            ->formatStateUsing(fn (?array $state): string => self::resolveCampaignNames($state))
                            ->columnSpanFull(),
                        TextEntry::make('assigned_user_ids')
                            ->label('Assigned users')
                            ->formatStateUsing(fn (?array $state): string => self::resolveUserNames($state))
                            ->columnSpanFull(),
                        TextEntry::make('assigned_role_ids')
                            ->label('Assigned roles')
                            ->formatStateUsing(fn (?array $state): string => self::resolveRoleNames($state))
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

    private static function resolveUserNames(?array $state): string
    {
        if (blank($state)) {
            return '—';
        }

        return User::query()
            ->whereIn('id', $state)
            ->orderBy('name')
            ->get()
            ->map(fn (User $user): string => trim($user->name ?: "{$user->first_name} {$user->last_name}") ?: $user->email)
            ->join(', ') ?: '—';
    }

    private static function resolveRoleNames(?array $state): string
    {
        if (blank($state)) {
            return '—';
        }

        return Role::query()->whereIn('id', $state)->orderBy('name')->pluck('name')->join(', ') ?: '—';
    }
}
