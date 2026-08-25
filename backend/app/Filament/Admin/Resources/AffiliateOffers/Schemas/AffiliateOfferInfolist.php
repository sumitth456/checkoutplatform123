<?php

namespace App\Filament\Admin\Resources\AffiliateOffers\Schemas;

use App\Filament\Admin\Support\Infolists\AdminInfolistComponents;
use App\Models\Campaign;
use App\Models\LandingPage;
use App\Models\Product;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class AffiliateOfferInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                AdminInfolistComponents::summaryHeader(),
                AdminInfolistComponents::activityTimeline('Offer timeline'),
                AdminInfolistComponents::detailsSection('Offer details', [
                    Grid::make(3)->schema([
                        TextEntry::make('uuid')->copyable(),
                        TextEntry::make('name'),
                        IconEntry::make('is_active')->boolean()->label('Active'),
                        TextEntry::make('commission_type')->badge(),
                        TextEntry::make('commission_value'),
                        TextEntry::make('recurring_commission_mode')->badge(),
                        TextEntry::make('recurring_commission_rebill_limit')->placeholder('—'),
                        TextEntry::make('affiliates_count')->counts('affiliates')->label('Affiliates'),
                        TextEntry::make('tracking_links_count')->counts('trackingLinks')->label('Tracking links'),
                        TextEntry::make('created_at')->dateTime(),
                        TextEntry::make('updated_at')->dateTime(),
                        TextEntry::make('description')->columnSpanFull()->placeholder('—'),
                        TextEntry::make('allowed_campaign_ids')
                            ->label('Allowed campaigns')
                            ->formatStateUsing(fn (?array $state): string => blank($state) ? '—' : Campaign::query()->whereIn('id', $state)->pluck('name')->join(', '))
                            ->columnSpanFull(),
                        TextEntry::make('allowed_product_ids')
                            ->label('Allowed products')
                            ->formatStateUsing(fn (?array $state): string => blank($state) ? '—' : Product::query()->whereIn('id', $state)->pluck('name')->join(', '))
                            ->columnSpanFull(),
                        TextEntry::make('allowed_landing_page_ids')
                            ->label('Allowed landing pages')
                            ->formatStateUsing(fn (?array $state): string => blank($state) ? '—' : LandingPage::query()->whereIn('id', $state)->pluck('name')->join(', '))
                            ->columnSpanFull(),
                    ]),
                ]),
            ]);
    }
}
