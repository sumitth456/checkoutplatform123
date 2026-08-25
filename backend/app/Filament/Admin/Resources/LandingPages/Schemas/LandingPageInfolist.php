<?php

namespace App\Filament\Admin\Resources\LandingPages\Schemas;

use App\Filament\Admin\Support\Infolists\AdminInfolistComponents;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class LandingPageInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                AdminInfolistComponents::summaryHeader(),
                AdminInfolistComponents::activityTimeline('Landing page timeline'),
                AdminInfolistComponents::detailsSection('Page details', [
                    Grid::make(3)->schema([
                        TextEntry::make('name'),
                        TextEntry::make('slug')->copyable(),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('campaign.name')->label('Campaign')->placeholder('—'),
                        TextEntry::make('publicUrl')
                            ->label('Public URL')
                            ->state(fn ($record): string => $record->publicUrl())
                            ->url(fn ($record): string => $record->publicUrl(), true)
                            ->copyable(),
                        TextEntry::make('published_at')->dateTime()->placeholder('—'),
                        TextEntry::make('view_count')->label('Views')->numeric(),
                        TextEntry::make('conversion_count')->label('Conversions')->numeric(),
                        TextEntry::make('revenue_total')->label('Revenue')->money('USD'),
                        TextEntry::make('conversion_rate')
                            ->label('Conversion rate')
                            ->state(fn ($record): string => $record->conversionRate().'%'),
                        TextEntry::make('seo_title')->placeholder('—'),
                        TextEntry::make('meta_description')->placeholder('—')->columnSpanFull(),
                    ]),
                ]),
            ]);
    }
}
