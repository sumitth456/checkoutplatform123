<?php

namespace App\Filament\Admin\Resources\FulfillmentHouses\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FulfillmentHouseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Section::make('General')->columnSpanFull()->schema([
                Grid::make(2)->schema([
                    TextInput::make('name')->required()->maxLength(255),
                    TextInput::make('code')->required()->maxLength(50)->unique(ignoreRecord: true),
                    TextInput::make('status')->default('active')->maxLength(50),
                    Toggle::make('is_active')->default(true),
                    TextInput::make('contact_email')->email()->maxLength(255),
                    TextInput::make('contact_phone')->tel()->maxLength(40),
                    TextInput::make('api_endpoint')->url()->columnSpanFull(),
                    KeyValue::make('settings')->columnSpanFull(),
                ]),
            ]),
        ]);
    }
}
