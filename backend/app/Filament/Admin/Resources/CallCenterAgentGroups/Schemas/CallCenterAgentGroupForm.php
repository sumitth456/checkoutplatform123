<?php

namespace App\Filament\Admin\Resources\CallCenterAgentGroups\Schemas;

use App\Models\Campaign;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class CallCenterAgentGroupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Group details')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('call_center_id')
                                ->label('Call center')
                                ->relationship('callCenter', 'name')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->live(),
                            TextInput::make('name')
                                ->required()
                                ->maxLength(255),
                        ]),
                        Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
                Section::make('Campaigns, permissions & agents')
                    ->columnSpanFull()
                    ->schema([
                        Select::make('assigned_campaign_ids')
                            ->label('Assigned campaigns')
                            ->multiple()
                            ->options(fn (): array => Campaign::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->preload(),
                        KeyValue::make('permissions')
                            ->keyLabel('Permission')
                            ->valueLabel('Value')
                            ->columnSpanFull(),
                        Select::make('agents')
                            ->label('Agents')
                            ->relationship(
                                'agents',
                                'name',
                                fn ($query, Get $get) => $query->when(
                                    filled($get('call_center_id')),
                                    fn ($query) => $query->where('call_center_id', $get('call_center_id')),
                                ),
                            )
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
