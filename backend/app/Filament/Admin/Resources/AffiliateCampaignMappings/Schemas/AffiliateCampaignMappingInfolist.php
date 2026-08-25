<?php

namespace App\Filament\Admin\Resources\AffiliateCampaignMappings\Schemas;

use App\Filament\Admin\Support\Infolists\AdminInfolistComponents;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class AffiliateCampaignMappingInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                AdminInfolistComponents::summaryHeader(),
                AdminInfolistComponents::activityTimeline('Campaign mapping timeline'),
                AdminInfolistComponents::detailsSection('Mapping details', [
                    Grid::make(3)->schema([
                        TextEntry::make('uuid')->copyable(),
                        TextEntry::make('affiliate.name')->label('Affiliate'),
                        TextEntry::make('platformCampaign.name')->label('Campaign'),
                        TextEntry::make('offer.name')->label('Offer')->placeholder('—'),
                        IconEntry::make('is_active')->boolean()->label('Active'),
                        TextEntry::make('commission_type')->badge()->placeholder('—'),
                        TextEntry::make('commission_value')->placeholder('—'),
                        TextEntry::make('created_at')->dateTime(),
                        TextEntry::make('updated_at')->dateTime(),
                    ]),
                ]),
            ]);
    }
}
