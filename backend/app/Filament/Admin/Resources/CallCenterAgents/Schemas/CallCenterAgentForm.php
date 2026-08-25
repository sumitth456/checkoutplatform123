<?php

namespace App\Filament\Admin\Resources\CallCenterAgents\Schemas;

use App\Models\Campaign;
use App\Modules\CallCenter\Enums\AgentStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CallCenterAgentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Agent profile')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('name')
                                ->required()
                                ->maxLength(255),
                            TextInput::make('username')
                                ->required()
                                ->maxLength(120)
                                ->alphaDash(),
                            TextInput::make('email')
                                ->email()
                                ->required()
                                ->maxLength(255),
                            TextInput::make('phone')
                                ->tel()
                                ->maxLength(40),
                            TextInput::make('extension')
                                ->maxLength(20),
                            TextInput::make('role_label')
                                ->label('Role label')
                                ->maxLength(120),
                            Select::make('status')
                                ->options(collect(AgentStatus::cases())->mapWithKeys(
                                    fn (AgentStatus $status): array => [$status->value => str($status->name)->headline()]
                                )->all())
                                ->required()
                                ->default(AgentStatus::Offline->value)
                                ->native(false),
                            Select::make('call_center_id')
                                ->label('Call center')
                                ->relationship('callCenter', 'name')
                                ->searchable()
                                ->preload()
                                ->required(),
                        ]),
                    ]),
                Section::make('Campaigns & permissions')
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
                    ]),
                Section::make('Session')
                    ->columnSpanFull()
                    ->collapsed()
                    ->schema([
                        Grid::make(2)->schema([
                            Toggle::make('is_logged_in')
                                ->label('Logged in')
                                ->disabled(),
                            DateTimePicker::make('last_login_at')
                                ->label('Last login')
                                ->disabled(),
                        ]),
                    ])
                    ->visibleOn(['edit']),
            ]);
    }
}
