<?php

namespace App\Filament\Admin\Resources\FulfillmentBatches\Schemas;

use App\Models\Warehouse;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FulfillmentBatchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Section::make('Batch')->columnSpanFull()->schema([
                Grid::make(2)->schema([
                    TextInput::make('name')->required()->maxLength(255),
                    Select::make('status')
                        ->options([
                            'pending' => 'Pending',
                            'processing' => 'Processing',
                            'completed' => 'Completed',
                            'failed' => 'Failed',
                        ])
                        ->default('pending')
                        ->required()
                        ->native(false),
                    Select::make('warehouse_id')
                        ->label('Warehouse')
                        ->options(fn (): array => Warehouse::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->preload(),
                    TextInput::make('record_count')->numeric()->default(0),
                    DateTimePicker::make('scheduled_at')->native(false),
                    DateTimePicker::make('processed_at')->native(false),
                ]),
            ]),
        ]);
    }
}
