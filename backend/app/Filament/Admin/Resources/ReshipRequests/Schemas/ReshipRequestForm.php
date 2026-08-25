<?php

namespace App\Filament\Admin\Resources\ReshipRequests\Schemas;

use App\Models\Fulfillment;
use App\Models\Order;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ReshipRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Section::make('Reship request')->columnSpanFull()->schema([
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
                            'pending' => 'Pending',
                            'approved' => 'Approved',
                            'rejected' => 'Rejected',
                            'completed' => 'Completed',
                        ])
                        ->default('pending')
                        ->required()
                        ->native(false),
                    Textarea::make('reason')->required()->rows(3)->columnSpanFull(),
                    Textarea::make('notes')->rows(2)->columnSpanFull(),
                ]),
            ]),
        ]);
    }
}
