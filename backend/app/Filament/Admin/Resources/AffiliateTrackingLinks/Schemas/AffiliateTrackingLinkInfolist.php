<?php

namespace App\Filament\Admin\Resources\AffiliateTrackingLinks\Schemas;

use App\Filament\Admin\Support\Infolists\AdminInfolistComponents;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class AffiliateTrackingLinkInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                AdminInfolistComponents::summaryHeader(),
                AdminInfolistComponents::activityTimeline('Tracking link timeline'),
                AdminInfolistComponents::detailsSection('Tracking link details', [
                    Grid::make(3)->schema([
                        TextEntry::make('uuid')->copyable(),
                        TextEntry::make('name')->placeholder('—'),
                        TextEntry::make('code')->copyable(),
                        TextEntry::make('affiliate.name')->label('Affiliate'),
                        TextEntry::make('offer.name')->label('Offer')->placeholder('—'),
                        TextEntry::make('platformCampaign.name')->label('Campaign')->placeholder('—'),
                        TextEntry::make('landingPage.name')->label('Landing page')->placeholder('—'),
                        IconEntry::make('is_active')->boolean()->label('Active'),
                        TextEntry::make('click_count')->numeric(),
                        TextEntry::make('conversion_count')->numeric(),
                        TextEntry::make('destination_url')->columnSpanFull()->copyable(),
                        TextEntry::make('generated_url')->columnSpanFull()->copyable(),
                        TextEntry::make('created_at')->dateTime(),
                        TextEntry::make('updated_at')->dateTime(),
                    ]),
                ]),
            ]);
    }
}
