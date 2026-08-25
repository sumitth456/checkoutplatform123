<?php

namespace App\Filament\Admin\Resources\ShippingRules\Schemas;

use App\Models\ShippingCarrier;
use App\Models\Warehouse;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ShippingRuleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Section::make('Rule')->columnSpanFull()->schema([
                Grid::make(2)->schema([
                    TextInput::make('name')->required()->maxLength(255),
                    TextInput::make('priority')->numeric()->default(100)->required(),
                    Select::make('warehouse_id')
                        ->label('Warehouse')
                        ->options(fn (): array => Warehouse::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->preload()
                        ->required(),
                    Select::make('shipping_carrier_id')
                        ->label('Carrier')
                        ->options(fn (): array => ShippingCarrier::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->preload()
                        ->required(),
                    Toggle::make('is_active')->default(true),
                    KeyValue::make('conditions')->columnSpanFull(),
                ]),
            ]),
        ]);
    }
}
