<?php

namespace App\Filament\Admin\Resources\ReshipRequests\Schemas;

use App\Filament\Admin\Resources\DelayFulfillment\DelayFulfillmentResource;
use App\Filament\Admin\Resources\Orders\OrderResource;
use App\Filament\Admin\Support\Infolists\AdminInfolistComponents;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class ReshipRequestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            AdminInfolistComponents::summaryHeader(),
            AdminInfolistComponents::activityTimeline('Reship request timeline'),
            AdminInfolistComponents::detailsSection('Request details', [
                Grid::make(3)->schema([
                    TextEntry::make('uuid')->copyable(),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('order.order_number')
                        ->label('Order')
                        ->url(fn ($record): ?string => $record->order
                            ? OrderResource::getUrl('view', ['record' => $record->order])
                            : null),
                    TextEntry::make('fulfillment.uuid')
                        ->label('Fulfillment')
                        ->url(fn ($record): ?string => $record->fulfillment
                            ? DelayFulfillmentResource::getUrl('view', ['record' => $record->fulfillment])
                            : null)
                        ->placeholder('—'),
                    TextEntry::make('requester.name')->label('Requested by')->placeholder('—'),
                    TextEntry::make('reason')->columnSpanFull(),
                    TextEntry::make('notes')->placeholder('—')->columnSpanFull(),
                    TextEntry::make('created_at')->dateTime(),
                    TextEntry::make('updated_at')->dateTime(),
                ]),
            ]),
        ]);
    }
}
