<?php

namespace App\Filament\Admin\Resources\AffiliateClicks\Schemas;

use App\Filament\Admin\Support\Infolists\AdminInfolistComponents;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class AffiliateClickInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                AdminInfolistComponents::summaryHeader(),
                AdminInfolistComponents::detailsSection('Click details', [
                    Grid::make(3)->schema([
                        TextEntry::make('uuid')->copyable(),
                        TextEntry::make('click_id')->copyable(),
                        TextEntry::make('affiliate.name')->label('Affiliate'),
                        TextEntry::make('offer.name')->label('Offer')->placeholder('—'),
                        TextEntry::make('platformCampaign.name')->label('Campaign')->placeholder('—'),
                        TextEntry::make('trackingLink.code')->label('Tracking link')->placeholder('—'),
                        IconEntry::make('is_unique')->boolean()->label('Unique'),
                        TextEntry::make('clicked_at')->dateTime(),
                        TextEntry::make('sub_id_1')->placeholder('—'),
                        TextEntry::make('sub_id_2')->placeholder('—'),
                        TextEntry::make('sub_id_3')->placeholder('—'),
                        TextEntry::make('sub_id_4')->placeholder('—'),
                        TextEntry::make('sub_id_5')->placeholder('—'),
                        TextEntry::make('utm_source')->placeholder('—'),
                        TextEntry::make('utm_medium')->placeholder('—'),
                        TextEntry::make('utm_campaign')->placeholder('—'),
                        TextEntry::make('utm_content')->placeholder('—'),
                        TextEntry::make('ip_address')->placeholder('—'),
                        TextEntry::make('browser')->placeholder('—'),
                        TextEntry::make('os')->placeholder('—'),
                        TextEntry::make('device_type')->placeholder('—'),
                        TextEntry::make('country_code')->placeholder('—'),
                        TextEntry::make('landing_url')->columnSpanFull()->placeholder('—'),
                        TextEntry::make('referrer_url')->columnSpanFull()->placeholder('—'),
                        TextEntry::make('user_agent')->columnSpanFull()->placeholder('—'),
                    ]),
                ]),
            ]);
    }
}
