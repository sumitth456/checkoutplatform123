<?php

namespace App\Filament\Admin\Resources\WarehouseSettings\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WarehouseSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('API connection')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('name')
                                ->required()
                                ->maxLength(255),
                            Toggle::make('is_active')
                                ->default(true),
                            TextInput::make('base_url')
                                ->label('Base URL')
                                ->url()
                                ->required()
                                ->maxLength(500),
                            TextInput::make('create_shipment_path')
                                ->label('Create shipment path')
                                ->required()
                                ->maxLength(255)
                                ->default('/v1/shipments'),
                            TextInput::make('timeout_seconds')
                                ->numeric()
                                ->minValue(1)
                                ->maxValue(120)
                                ->required(),
                            TextInput::make('api_key')
                                ->label('API key')
                                ->password()
                                ->revealable()
                                ->maxLength(500)
                                ->dehydrated(fn (?string $state): bool => filled($state))
                                ->helperText('Leave blank to keep the current key.'),
                        ]),
                    ]),
            ]);
    }
}
