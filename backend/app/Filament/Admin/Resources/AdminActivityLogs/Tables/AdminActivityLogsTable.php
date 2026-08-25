<?php

namespace App\Filament\Admin\Resources\AdminActivityLogs\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AdminActivityLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->dateTime()->sortable(),
                TextColumn::make('module')->searchable(),
                TextColumn::make('event')->searchable(),
                TextColumn::make('description')->limit(60)->wrap(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
