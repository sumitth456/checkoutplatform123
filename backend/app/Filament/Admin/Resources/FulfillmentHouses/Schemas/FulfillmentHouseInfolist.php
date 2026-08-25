<?php

namespace App\Filament\Admin\Resources\FulfillmentHouses\Schemas;

use App\Filament\Admin\Support\Infolists\AdminInfolistComponents;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class FulfillmentHouseInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            AdminInfolistComponents::summaryHeader(),
            AdminInfolistComponents::activityTimeline('Fulfillment house timeline'),
            AdminInfolistComponents::detailsSection('Fulfillment house details', [
                Grid::make(3)->schema([
                    TextEntry::make('uuid')->copyable(),
                    TextEntry::make('name'),
                    TextEntry::make('code'),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('is_active')->boolean()->label('Active'),
                    TextEntry::make('contact_email')->placeholder('—'),
                    TextEntry::make('contact_phone')->placeholder('—'),
                    TextEntry::make('api_endpoint')->placeholder('—')->columnSpanFull(),
                    TextEntry::make('warehouses_count')->counts('warehouses')->label('Warehouses'),
                    TextEntry::make('created_at')->dateTime(),
                    TextEntry::make('updated_at')->dateTime(),
                ]),
            ]),
        ]);
    }
}
