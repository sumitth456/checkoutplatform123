<?php

namespace App\Filament\Admin\Resources\ShippingCarriers\Schemas;

use App\Filament\Admin\Support\Infolists\AdminInfolistComponents;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class ShippingCarrierInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            AdminInfolistComponents::summaryHeader(),
            AdminInfolistComponents::activityTimeline('Carrier timeline'),
            AdminInfolistComponents::detailsSection('Carrier details', [
                Grid::make(3)->schema([
                    TextEntry::make('uuid')->copyable(),
                    TextEntry::make('name'),
                    TextEntry::make('code'),
                    TextEntry::make('is_active')->boolean()->label('Active'),
                    TextEntry::make('tracking_url_template')->placeholder('—')->columnSpanFull(),
                    TextEntry::make('created_at')->dateTime(),
                    TextEntry::make('updated_at')->dateTime(),
                ]),
            ]),
        ]);
    }
}
