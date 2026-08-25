<?php

namespace App\Filament\Admin\Resources\AffiliatePromoMaterials\Schemas;

use App\Filament\Admin\Support\Infolists\AdminInfolistComponents;
use App\Models\Affiliate;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class AffiliatePromoMaterialInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                AdminInfolistComponents::summaryHeader(),
                AdminInfolistComponents::activityTimeline('Promo material timeline'),
                AdminInfolistComponents::detailsSection('Promo material details', [
                    Grid::make(3)->schema([
                        TextEntry::make('uuid')->copyable(),
                        TextEntry::make('name'),
                        TextEntry::make('type')->badge(),
                        IconEntry::make('is_active')->boolean()->label('Active'),
                        TextEntry::make('download_count')->numeric(),
                        TextEntry::make('offer.name')->label('Offer')->placeholder('—'),
                        TextEntry::make('landingPage.name')->label('Landing page')->placeholder('—'),
                        TextEntry::make('coupon.code')->label('Coupon')->placeholder('—'),
                        TextEntry::make('file_path')->placeholder('—'),
                        TextEntry::make('file_url')->placeholder('—')->columnSpanFull(),
                        TextEntry::make('allowed_affiliate_ids')
                            ->label('Allowed affiliates')
                            ->formatStateUsing(fn (?array $state): string => blank($state) ? 'All' : Affiliate::query()->whereIn('id', $state)->pluck('name')->join(', '))
                            ->columnSpanFull(),
                        TextEntry::make('description')->columnSpanFull()->placeholder('—'),
                        TextEntry::make('created_at')->dateTime(),
                        TextEntry::make('updated_at')->dateTime(),
                    ]),
                ]),
            ]);
    }
}
