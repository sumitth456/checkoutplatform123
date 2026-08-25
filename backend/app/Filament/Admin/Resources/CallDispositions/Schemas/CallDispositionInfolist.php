<?php

namespace App\Filament\Admin\Resources\CallDispositions\Schemas;

use App\Filament\Admin\Support\Infolists\AdminInfolistComponents;
use Filament\Infolists\Components\ColorEntry;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class CallDispositionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                AdminInfolistComponents::summaryHeader(),
                AdminInfolistComponents::activityTimeline('Disposition timeline'),
                AdminInfolistComponents::detailsSection('Disposition details', [
                    Grid::make(3)->schema([
                        TextEntry::make('uuid')->label('UUID')->copyable(),
                        TextEntry::make('name'),
                        TextEntry::make('code')->copyable(),
                        ColorEntry::make('color'),
                        TextEntry::make('sort_order')->label('Sort order'),
                        IconEntry::make('is_active')->label('Active')->boolean(),
                        IconEntry::make('counts_as_sale')->label('Counts as sale')->boolean(),
                        TextEntry::make('call_logs_count')->counts('callLogs')->label('Call logs'),
                        TextEntry::make('created_at')->dateTime(),
                        TextEntry::make('updated_at')->dateTime(),
                        TextEntry::make('description')->columnSpanFull()->placeholder('—'),
                    ]),
                ]),
            ]);
    }
}
