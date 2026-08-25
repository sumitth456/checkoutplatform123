<?php

namespace App\Filament\Admin\Resources\Warehouses\Schemas;

use App\Models\FulfillmentHouse;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WarehouseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Section::make('General')->columnSpanFull()->schema([
                Grid::make(2)->schema([
                    Select::make('fulfillment_house_id')
                        ->label('Fulfillment house')
                        ->options(fn (): array => FulfillmentHouse::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->preload()
                        ->required(),
                    TextInput::make('name')->required()->maxLength(255),
                    TextInput::make('code')->required()->maxLength(50)->unique(ignoreRecord: true),
                    TextInput::make('timezone')->default('UTC'),
                    Toggle::make('is_active')->default(true),
                ]),
            ]),
            Section::make('Address')->columnSpanFull()->schema([
                Grid::make(2)->schema([
                    TextInput::make('address_line1')->maxLength(255),
                    TextInput::make('address_line2')->maxLength(255),
                    TextInput::make('city')->maxLength(100),
                    TextInput::make('state')->maxLength(100),
                    TextInput::make('postal_code')->maxLength(20),
                    TextInput::make('country_code')->maxLength(5)->default('US'),
                ]),
            ]),
        ]);
    }
}
