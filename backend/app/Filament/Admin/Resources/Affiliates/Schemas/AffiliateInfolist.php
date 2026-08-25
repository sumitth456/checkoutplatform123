<?php

namespace App\Filament\Admin\Resources\Affiliates\Schemas;

use App\Filament\Admin\Support\Infolists\AdminInfolistComponents;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class AffiliateInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                AdminInfolistComponents::summaryHeader(),
                AdminInfolistComponents::activityTimeline('Affiliate timeline'),
                AdminInfolistComponents::detailsSection('Affiliate details', [
                    Grid::make(3)->schema([
                        TextEntry::make('uuid')->label('UUID')->copyable(),
                        TextEntry::make('name'),
                        TextEntry::make('company')->placeholder('—'),
                        TextEntry::make('username'),
                        TextEntry::make('email')->copyable(),
                        TextEntry::make('phone')->placeholder('—'),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('country_code')->label('Country')->placeholder('—'),
                        TextEntry::make('tracking_id')->label('Tracking ID')->copyable(),
                        TextEntry::make('api_key')->label('API key')->copyable()->placeholder('—'),
                        TextEntry::make('default_commission_type')->label('Commission type')->badge(),
                        TextEntry::make('default_commission_value')->label('Commission value'),
                        TextEntry::make('recurring_commission_mode')->label('Recurring mode')->badge(),
                        TextEntry::make('recurring_commission_rebill_limit')->label('Rebill limit')->placeholder('—'),
                        TextEntry::make('cookie_duration_days')->label('Cookie days'),
                        TextEntry::make('payment_method')->badge()->placeholder('—'),
                        TextEntry::make('postback_url')->label('Postback URL')->placeholder('—')->columnSpanFull(),
                        TextEntry::make('allow_coupon_attribution')->label('Coupon attribution')->formatStateUsing(fn (bool $state): string => $state ? 'Yes' : 'No'),
                        TextEntry::make('allow_self_referrals')->label('Self referrals')->formatStateUsing(fn (bool $state): string => $state ? 'Yes' : 'No'),
                        TextEntry::make('pending_commission')->money('USD'),
                        TextEntry::make('paid_commission')->money('USD'),
                        TextEntry::make('lifetime_revenue')->money('USD'),
                        TextEntry::make('total_clicks')->numeric(),
                        TextEntry::make('total_conversions')->numeric(),
                        TextEntry::make('offers_count')->counts('offers')->label('Offers'),
                        TextEntry::make('clicks_count')->counts('clicks')->label('Clicks'),
                        TextEntry::make('conversions_count')->counts('conversions')->label('Conversions'),
                        TextEntry::make('approved_at')->dateTime()->placeholder('—'),
                        TextEntry::make('last_login_at')->dateTime()->placeholder('—'),
                        TextEntry::make('created_at')->dateTime(),
                        TextEntry::make('updated_at')->dateTime(),
                        TextEntry::make('offers.name')
                            ->label('Assigned offers')
                            ->badge()
                            ->separator(',')
                            ->placeholder('—')
                            ->columnSpanFull(),
                        TextEntry::make('notes')->columnSpanFull()->placeholder('—'),
                        TextEntry::make('address')
                            ->formatStateUsing(fn (?array $state): string => blank($state) ? '—' : json_encode($state, JSON_PRETTY_PRINT))
                            ->columnSpanFull(),
                        TextEntry::make('tax_information')
                            ->formatStateUsing(fn (?array $state): string => blank($state) ? '—' : json_encode($state, JSON_PRETTY_PRINT))
                            ->columnSpanFull(),
                    ]),
                ]),
            ]);
    }
}
