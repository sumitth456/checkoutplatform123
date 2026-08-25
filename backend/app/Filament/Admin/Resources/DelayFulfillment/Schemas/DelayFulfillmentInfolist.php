<?php

namespace App\Filament\Admin\Resources\DelayFulfillment\Schemas;

use App\Filament\Admin\Resources\Orders\OrderResource;
use App\Filament\Admin\Support\Infolists\AdminInfolistComponents;
use App\Filament\Admin\Support\Infolists\FulfillmentInfolistComponents;
use App\Models\Fulfillment;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class DelayFulfillmentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                AdminInfolistComponents::summaryHeader(),
                FulfillmentInfolistComponents::fulfillmentTimeline('Fulfillment status timeline'),
                AdminInfolistComponents::detailsSection('Schedule & shipment', [
                    Grid::make(3)->schema([
                        TextEntry::make('uuid')->copyable(),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('delay_days')->label('Delay (days)'),
                        TextEntry::make('scheduled_ship_date')->date(),
                        TextEntry::make('days_until_ship')
                            ->label('Days until ship')
                            ->state(fn (Fulfillment $record): string => (string) $record->daysUntilShip()),
                        TextEntry::make('shipped_at')->dateTime()->placeholder('—'),
                        TextEntry::make('order.order_number')
                            ->label('Order')
                            ->url(fn (Fulfillment $record): ?string => $record->order
                                ? OrderResource::getUrl('view', ['record' => $record->order])
                                : null),
                        TextEntry::make('order.customer_email')
                            ->label('Customer email')
                            ->placeholder('—'),
                        TextEntry::make('warehouse_reference')->placeholder('—'),
                        TextEntry::make('tracking_number')->copyable()->placeholder('—'),
                        TextEntry::make('carrier')->placeholder('—'),
                        TextEntry::make('shipment_status')->badge()->placeholder('—'),
                        TextEntry::make('delivery_status')->badge()->placeholder('—'),
                        TextEntry::make('failure_reason')->columnSpanFull()->placeholder('—'),
                    ]),
                ]),
                AdminInfolistComponents::detailsSection('Warehouse request', [
                    TextEntry::make('warehouse_request')
                        ->formatStateUsing(fn (?array $state): string => $state !== null
                            ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                            : '—')
                        ->columnSpanFull()
                        ->prose(),
                ]),
                AdminInfolistComponents::detailsSection('Warehouse response', [
                    TextEntry::make('warehouse_response')
                        ->formatStateUsing(fn (?array $state): string => $state !== null
                            ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                            : '—')
                        ->columnSpanFull()
                        ->prose(),
                ]),
            ]);
    }
}
