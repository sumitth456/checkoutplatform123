<?php

namespace App\Filament\Admin\Resources\Orders\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class ActivityTimelineRelationManager extends RelationManager
{
    protected static string $relationship = 'adminActivityLogs';

    public function table(Table $table): Table
    {
        return $table->columns([]);
    }
}
