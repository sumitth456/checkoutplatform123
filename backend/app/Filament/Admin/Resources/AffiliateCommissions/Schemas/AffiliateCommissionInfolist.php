<?php

namespace App\Filament\Admin\Resources\AffiliateCommissions\Schemas;

use App\Filament\Admin\Support\Infolists\AdminInfolistComponents;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class AffiliateCommissionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                AdminInfolistComponents::summaryHeader(),
                AdminInfolistComponents::detailsSection('Commission details', [
                    Grid::make(3)->schema([
                        TextEntry::make('uuid')->copyable(),
                        TextEntry::make('affiliate.name')->label('Affiliate'),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('commission_type')->badge(),
                        TextEntry::make('commission_rate'),
                        TextEntry::make('order_amount')->money('USD'),
                        TextEntry::make('commission_amount')->money('USD'),
                        TextEntry::make('currency'),
                        TextEntry::make('rule_source')->placeholder('—'),
                        TextEntry::make('rebill_number')->placeholder('—'),
                        TextEntry::make('order.order_number')->label('Order')->placeholder('—'),
                        TextEntry::make('payout.uuid')->label('Payout')->placeholder('—'),
                        TextEntry::make('earned_at')->dateTime(),
                        TextEntry::make('approved_at')->dateTime()->placeholder('—'),
                        TextEntry::make('paid_at')->dateTime()->placeholder('—'),
                        TextEntry::make('notes')->columnSpanFull()->placeholder('—'),
                    ]),
                ]),
            ]);
    }
}
