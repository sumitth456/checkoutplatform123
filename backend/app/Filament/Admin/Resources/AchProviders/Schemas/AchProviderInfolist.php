<?php

namespace App\Filament\Admin\Resources\AchProviders\Schemas;

use App\Models\AchProvider;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AchProviderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Provider')->schema([
                Grid::make(3)->schema([
                    TextEntry::make('name'),
                    TextEntry::make('slug'),
                    TextEntry::make('provider')->label('Driver')->badge(),
                    TextEntry::make('status')->badge(),
                    IconEntry::make('sandbox_mode')->boolean()->label('Sandbox mode'),
                    IconEntry::make('is_default')->boolean()->label('Default provider'),
                    TextEntry::make('priority'),
                    TextEntry::make('merchant_id')->placeholder('—'),
                    TextEntry::make('uuid')->copyable()->columnSpanFull(),
                ]),
            ]),
        ]);
    }
}
