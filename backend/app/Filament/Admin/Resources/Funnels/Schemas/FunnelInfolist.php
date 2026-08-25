<?php

namespace App\Filament\Admin\Resources\Funnels\Schemas;

use App\Filament\Admin\Support\Infolists\AdminInfolistComponents;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class FunnelInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                AdminInfolistComponents::summaryHeader(),
                AdminInfolistComponents::activityTimeline('Funnel timeline'),
                AdminInfolistComponents::detailsSection('Funnel details', [
                    Grid::make(2)->schema([
                        TextEntry::make('name'),
                        TextEntry::make('slug'),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('currency'),
                        TextEntry::make('description')->columnSpanFull()->placeholder('—'),
                    ]),
                ]),
            ]);
    }
}
