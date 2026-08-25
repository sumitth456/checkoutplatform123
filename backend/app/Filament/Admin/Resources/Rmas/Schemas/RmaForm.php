<?php

namespace App\Filament\Admin\Resources\Rmas\Schemas;

use App\Models\Fulfillment;
use App\Models\Order;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RmaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Section::make('RMA')->columnSpanFull()->schema([
                Grid::make(2)->schema([
                    Select::make('order_id')
                        ->label('Order')
                        ->options(fn (): array => Order::query()->orderByDesc('id')->limit(200)->pluck('order_number', 'id')->all())
                        ->searchable()
                        ->required(),
                    Select::make('fulfillment_id')
                        ->label('Fulfillment')
                        ->options(fn (): array => Fulfillment::query()->orderByDesc('id')->limit(200)->pluck('uuid', 'id')->all())
                        ->searchable(),
                    Select::make('status')
                        ->options([
                            'open' => 'Open',
                            'received' => 'Received',
                            'closed' => 'Closed',
                            'rejected' => 'Rejected',
                        ])
                        ->default('open')
                        ->required()
                        ->native(false),
                    DateTimePicker::make('received_at')->native(false),
                    Textarea::make('reason')->required()->rows(3)->columnSpanFull(),
                    Textarea::make('notes')->rows(2)->columnSpanFull(),
                ]),
            ]),
        ]);
    }
}
