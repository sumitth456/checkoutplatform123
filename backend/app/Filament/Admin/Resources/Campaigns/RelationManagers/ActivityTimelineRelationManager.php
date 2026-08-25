<?php

namespace App\Filament\Admin\Resources\Campaigns\RelationManagers;

use App\Filament\Admin\Resources\AdminActivityLogs\AdminActivityLogResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ActivityTimelineRelationManager extends RelationManager
{
    protected static string $relationship = 'adminActivityLogs';

    protected static ?string $title = 'Activity timeline';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->dateTime()->sortable(),
                TextColumn::make('event')->badge(),
                TextColumn::make('description')->wrap(),
                TextColumn::make('user.name')->label('User')->placeholder('System'),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn ($record) => AdminActivityLogResource::getUrl('view', ['record' => $record]));
    }
}
