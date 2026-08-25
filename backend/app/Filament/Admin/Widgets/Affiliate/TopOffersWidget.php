<?php

namespace App\Filament\Admin\Widgets\Affiliate;

use App\Filament\Admin\Resources\AffiliateOffers\AffiliateOfferResource;
use App\Models\AffiliateOffer;
use App\Support\Admin\AdminAuthorization;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Number;

class TopOffersWidget extends TableWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Top offers';

    protected ?string $pollingInterval = '120s';

    public static function canView(): bool
    {
        return AdminAuthorization::can(auth()->user(), 'affiliate_dashboard.view');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                AffiliateOffer::query()
                    ->withCount(['trackingLinks', 'affiliates'])
                    ->where('is_active', true)
                    ->orderByDesc('tracking_links_count')
                    ->limit(10),
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->url(fn (AffiliateOffer $record): string => AffiliateOfferResource::getUrl('view', ['record' => $record])),
                Tables\Columns\TextColumn::make('commission_type')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => str($state->name ?? $state)->headline()),
                Tables\Columns\TextColumn::make('commission_value')
                    ->label('Rate')
                    ->formatStateUsing(fn (AffiliateOffer $record): string => $record->commission_type?->value === 'percentage'
                        ? number_format((float) $record->commission_value, 2).'%'
                        : Number::currency((float) $record->commission_value, 'USD')),
                Tables\Columns\TextColumn::make('affiliates_count')
                    ->label('Affiliates')
                    ->numeric(),
                Tables\Columns\TextColumn::make('tracking_links_count')
                    ->label('Links')
                    ->numeric(),
            ])
            ->paginated(false);
    }
}
