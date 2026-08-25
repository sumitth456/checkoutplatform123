<?php

namespace App\Filament\Admin\Resources\Products\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ActivityTimelineRelationManager extends RelationManager
{
    protected static string $relationship = 'adminActivityLogs';

    protected static ?string $title = 'Activity timeline';

    public function form(Schema $schema): Schema
    {
        return $schema;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('event')
                    ->badge()
                    ->sortable(),
                TextColumn::make('description')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('causer.name')
                    ->label('User')
                    ->placeholder('System'),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50])
            ->recordActions([])
            ->headerActions([])
            ->toolbarActions([]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return (string) $ownerRecord->adminActivityLogs()->count();
    }
}
