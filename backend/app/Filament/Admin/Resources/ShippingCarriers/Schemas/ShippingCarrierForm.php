<?php

namespace App\Filament\Admin\Resources\ShippingCarriers\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ShippingCarrierForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Section::make('Carrier')->columnSpanFull()->schema([
                Grid::make(2)->schema([
                    TextInput::make('name')->required()->maxLength(255),
                    TextInput::make('code')->required()->maxLength(50)->unique(ignoreRecord: true),
                    TextInput::make('tracking_url_template')
                        ->label('Tracking URL template')
                        ->placeholder('https://track.example.com/{tracking_number}')
                        ->columnSpanFull(),
                    Toggle::make('is_active')->default(true),
                ]),
            ]),
        ]);
    }
}
