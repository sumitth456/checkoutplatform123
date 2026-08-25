<?php

namespace App\Filament\Admin\Resources\CallDispositions\Schemas;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CallDispositionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Disposition')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('name')
                                ->required()
                                ->maxLength(255),
                            TextInput::make('code')
                                ->required()
                                ->maxLength(64)
                                ->unique(ignoreRecord: true)
                                ->alphaDash(),
                            ColorPicker::make('color')
                                ->default('#6366f1'),
                            TextInput::make('sort_order')
                                ->numeric()
                                ->default(0)
                                ->minValue(0),
                            Toggle::make('is_active')
                                ->label('Active')
                                ->default(true),
                            Toggle::make('counts_as_sale')
                                ->label('Counts as sale')
                                ->default(false),
                        ]),
                        Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
