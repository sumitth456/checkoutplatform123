<?php

namespace App\Filament\Admin\Resources\ShippingRules\Schemas;

use App\Filament\Admin\Support\Infolists\AdminInfolistComponents;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class ShippingRuleInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            AdminInfolistComponents::summaryHeader(),
            AdminInfolistComponents::activityTimeline('Shipping rule timeline'),
            AdminInfolistComponents::detailsSection('Rule details', [
                Grid::make(3)->schema([
                    TextEntry::make('uuid')->copyable(),
                    TextEntry::make('name'),
                    TextEntry::make('priority'),
                    TextEntry::make('warehouse.name')->label('Warehouse'),
                    TextEntry::make('shippingCarrier.name')->label('Carrier'),
                    TextEntry::make('is_active')->boolean()->label('Active'),
                    TextEntry::make('conditions')
                        ->formatStateUsing(fn (?array $state): string => blank($state) ? '—' : json_encode($state, JSON_PRETTY_PRINT))
                        ->columnSpanFull(),
                    TextEntry::make('created_at')->dateTime(),
                    TextEntry::make('updated_at')->dateTime(),
                ]),
            ]),
        ]);
    }
}
