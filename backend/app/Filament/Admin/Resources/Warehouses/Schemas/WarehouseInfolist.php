<?php

namespace App\Filament\Admin\Resources\Warehouses\Schemas;

use App\Filament\Admin\Support\Infolists\AdminInfolistComponents;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class WarehouseInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            AdminInfolistComponents::summaryHeader(),
            AdminInfolistComponents::activityTimeline('Warehouse timeline'),
            AdminInfolistComponents::detailsSection('Warehouse details', [
                Grid::make(3)->schema([
                    TextEntry::make('uuid')->copyable(),
                    TextEntry::make('name'),
                    TextEntry::make('code'),
                    TextEntry::make('fulfillmentHouse.name')->label('Fulfillment house'),
                    TextEntry::make('timezone'),
                    TextEntry::make('is_active')->boolean()->label('Active'),
                    TextEntry::make('address_line1')->placeholder('—'),
                    TextEntry::make('city')->placeholder('—'),
                    TextEntry::make('state')->placeholder('—'),
                    TextEntry::make('postal_code')->placeholder('—'),
                    TextEntry::make('country_code')->placeholder('—'),
                    TextEntry::make('created_at')->dateTime(),
                    TextEntry::make('updated_at')->dateTime(),
                ]),
            ]),
        ]);
    }
}
