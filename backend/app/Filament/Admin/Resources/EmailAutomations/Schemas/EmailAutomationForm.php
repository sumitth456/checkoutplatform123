<?php

namespace App\Filament\Admin\Resources\EmailAutomations\Schemas;

use App\Models\EmailTemplate;
use App\Modules\Email\Enums\EmailTrigger;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EmailAutomationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Automation')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('name')
                                ->required()
                                ->maxLength(255),
                            Select::make('trigger_event')
                                ->label('Trigger event')
                                ->options(collect(EmailTrigger::cases())->mapWithKeys(
                                    fn (EmailTrigger $trigger): array => [$trigger->value => $trigger->label()],
                                )->all())
                                ->required()
                                ->searchable()
                                ->native(false),
                            Select::make('email_template_id')
                                ->label('Email template')
                                ->options(fn (): array => EmailTemplate::query()
                                    ->where('is_active', true)
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->all())
                                ->searchable()
                                ->preload()
                                ->required(),
                            TextInput::make('delay_minutes')
                                ->label('Delay (minutes)')
                                ->numeric()
                                ->minValue(0)
                                ->default(0)
                                ->required(),
                            TextInput::make('priority')
                                ->numeric()
                                ->minValue(0)
                                ->default(100)
                                ->required(),
                            Toggle::make('is_active')
                                ->label('Active')
                                ->default(true),
                            KeyValue::make('conditions')
                                ->label('Conditions (JSON)')
                                ->columnSpanFull()
                                ->helperText('Optional key/value conditions evaluated when the trigger fires.'),
                        ]),
                    ]),
            ]);
    }
}
