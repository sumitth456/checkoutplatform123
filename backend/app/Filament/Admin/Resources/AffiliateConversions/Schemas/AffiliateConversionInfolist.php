<?php

namespace App\Filament\Admin\Resources\AffiliateConversions\Schemas;

use App\Filament\Admin\Support\Infolists\AdminInfolistComponents;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class AffiliateConversionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                AdminInfolistComponents::summaryHeader(),
                AdminInfolistComponents::detailsSection('Conversion details', [
                    Grid::make(3)->schema([
                        TextEntry::make('uuid')->copyable(),
                        TextEntry::make('affiliate.name')->label('Affiliate'),
                        TextEntry::make('conversion_type')->badge(),
                        TextEntry::make('revenue')->money('USD'),
                        TextEntry::make('commission_amount')->money('USD'),
                        TextEntry::make('currency'),
                        TextEntry::make('offer.name')->label('Offer')->placeholder('—'),
                        TextEntry::make('order.order_number')->label('Order')->placeholder('—'),
                        TextEntry::make('customer.email')->label('Customer')->placeholder('—'),
                        TextEntry::make('click.click_id')->label('Click')->placeholder('—'),
                        TextEntry::make('rebill_number')->placeholder('—'),
                        TextEntry::make('converted_at')->dateTime(),
                        TextEntry::make('sub_id_1')->placeholder('—'),
                        TextEntry::make('sub_id_2')->placeholder('—'),
                        TextEntry::make('sub_id_3')->placeholder('—'),
                        TextEntry::make('sub_id_4')->placeholder('—'),
                        TextEntry::make('sub_id_5')->placeholder('—'),
                    ]),
                ]),
            ]);
    }
}
