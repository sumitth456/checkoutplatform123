<?php

namespace App\Filament\Admin\Resources\Permissions\Schemas;

use App\Filament\Admin\Support\Infolists\AdminInfolistComponents;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class PermissionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                AdminInfolistComponents::summaryHeader(),
                AdminInfolistComponents::activityTimeline('Permission timeline'),
                AdminInfolistComponents::detailsSection('Permission details', [
                    Grid::make(2)->schema([
                        TextEntry::make('name'),
                        TextEntry::make('guard_name'),
                        TextEntry::make('roles_count')
                            ->label('Roles')
                            ->state(fn ($record): int => $record->roles()->count()),
                    ]),
                ]),
            ]);
    }
}
