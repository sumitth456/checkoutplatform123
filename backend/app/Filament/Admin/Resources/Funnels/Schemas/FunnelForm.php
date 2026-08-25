<?php

namespace App\Filament\Admin\Resources\Funnels\Schemas;

use App\Modules\Order\Enums\FunnelStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FunnelForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('General')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('name')->required()->maxLength(255),
                            TextInput::make('slug')->maxLength(255),
                            Select::make('status')
                                ->options(collect(FunnelStatus::cases())->mapWithKeys(
                                    fn (FunnelStatus $status): array => [$status->value => str($status->name)->headline()]
                                )->all())
                                ->required()
                                ->native(false),
                            TextInput::make('currency')->maxLength(3)->default('USD'),
                        ]),
                        Textarea::make('description')->rows(3)->columnSpanFull(),
                    ]),
            ]);
    }
}
