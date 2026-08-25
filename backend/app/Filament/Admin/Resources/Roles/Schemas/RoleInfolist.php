<?php

namespace App\Filament\Admin\Resources\Roles\Schemas;

use App\Filament\Admin\Support\Infolists\AdminInfolistComponents;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class RoleInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                AdminInfolistComponents::summaryHeader(),
                AdminInfolistComponents::activityTimeline('Role timeline'),
                AdminInfolistComponents::detailsSection('Role details', [
                    Grid::make(2)->schema([
                        TextEntry::make('name'),
                        TextEntry::make('description')->placeholder('—'),
                        TextEntry::make('permissions_count')
                            ->label('Permissions')
                            ->state(fn ($record): int => $record->permissions()->count()),
                        TextEntry::make('users_count')
                            ->label('Assigned users')
                            ->state(fn ($record): int => $record->users()->count()),
                        TextEntry::make('permissions.name')
                            ->label('Permissions')
                            ->badge()
                            ->columnSpanFull(),
                    ]),
                ]),
            ]);
    }
}
