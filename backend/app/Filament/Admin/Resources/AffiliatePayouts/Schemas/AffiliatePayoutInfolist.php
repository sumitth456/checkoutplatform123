<?php

namespace App\Filament\Admin\Resources\AffiliatePayouts\Schemas;

use App\Filament\Admin\Support\Infolists\AdminInfolistComponents;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class AffiliatePayoutInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                AdminInfolistComponents::summaryHeader(),
                AdminInfolistComponents::activityTimeline('Payout timeline'),
                AdminInfolistComponents::detailsSection('Payout details', [
                    Grid::make(3)->schema([
                        TextEntry::make('uuid')->copyable(),
                        TextEntry::make('affiliate.name')->label('Affiliate'),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('amount')->money('USD'),
                        TextEntry::make('currency'),
                        TextEntry::make('payment_method')->badge()->placeholder('—'),
                        TextEntry::make('reference')->placeholder('—'),
                        TextEntry::make('approver.name')->label('Approved by')->placeholder('—'),
                        TextEntry::make('approved_at')->dateTime()->placeholder('—'),
                        TextEntry::make('paid_at')->dateTime()->placeholder('—'),
                        TextEntry::make('created_at')->dateTime(),
                        TextEntry::make('notes')->columnSpanFull()->placeholder('—'),
                    ]),
                ]),
            ]);
    }
}
