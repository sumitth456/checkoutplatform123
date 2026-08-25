<?php

namespace App\Filament\Admin\Resources\Users\Schemas;

use App\Filament\Admin\Support\Infolists\AdminInfolistComponents;
use App\Models\User;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                AdminInfolistComponents::summaryHeader(),
                AdminInfolistComponents::activityTimeline('User activity'),
                AdminInfolistComponents::detailsSection('User details', [
                    Grid::make(3)->schema([
                        TextEntry::make('first_name')->placeholder('—'),
                        TextEntry::make('last_name')->placeholder('—'),
                        TextEntry::make('username')->copyable(),
                        TextEntry::make('email')->copyable(),
                        TextEntry::make('phone')->placeholder('—'),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('roles.name')->label('Role')->badge()->placeholder('—'),
                        TextEntry::make('permission_summary')
                            ->label('Permission summary')
                            ->bulleted()
                            ->state(fn (User $record): array => $record->permissionSummary())
                            ->columnSpanFull(),
                        TextEntry::make('permissions.name')
                            ->label('Custom permission overrides')
                            ->badge()
                            ->placeholder('None')
                            ->columnSpanFull(),
                        TextEntry::make('campaigns.name')
                            ->label('Campaigns')
                            ->badge()
                            ->placeholder('All campaigns (none restricted)'),
                        TextEntry::make('ip_whitelist')
                            ->label('IP whitelist')
                            ->bulleted()
                            ->placeholder('Any IP allowed')
                            ->formatStateUsing(fn (?array $state): array => $state ?? []),
                        IconEntry::make('two_factor_enabled')->boolean()->label('Two-factor enabled'),
                        IconEntry::make('require_password_change')->boolean()->label('Require password change'),
                        IconEntry::make('force_logout_other_devices')->boolean()->label('Force logout other devices'),
                        TextEntry::make('createdBy.name')->label('Created by')->placeholder('—'),
                        TextEntry::make('updatedBy.name')->label('Updated by')->placeholder('—'),
                        TextEntry::make('last_login_at')->dateTime()->placeholder('Never'),
                        TextEntry::make('last_login_ip')->placeholder('—'),
                        TextEntry::make('created_at')->dateTime(),
                        TextEntry::make('updated_at')->dateTime(),
                    ]),
                ]),
            ]);
    }
}
