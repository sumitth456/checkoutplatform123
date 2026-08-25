<?php

namespace App\Filament\Admin\Resources\AdminActivityLogs\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class AdminActivityLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('uuid'),
            TextEntry::make('module'),
            TextEntry::make('event'),
            TextEntry::make('description')->columnSpanFull(),
            TextEntry::make('created_at')->dateTime(),
        ]);
    }
}
