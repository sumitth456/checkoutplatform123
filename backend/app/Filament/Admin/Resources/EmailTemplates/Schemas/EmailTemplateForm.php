<?php

namespace App\Filament\Admin\Resources\EmailTemplates\Schemas;

use App\Modules\Email\Enums\EmailTrigger;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class EmailTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Template')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('name')
                                ->required()
                                ->maxLength(255)
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (Set $set, ?string $state, $record): void {
                                    if ($record !== null) {
                                        return;
                                    }

                                    $set('slug', Str::slug($state ?? ''));
                                }),
                            TextInput::make('slug')
                                ->required()
                                ->maxLength(255)
                                ->unique(ignoreRecord: true),
                            TextInput::make('category')
                                ->maxLength(255)
                                ->default('transactional'),
                            Select::make('trigger_event')
                                ->label('Trigger event')
                                ->options(collect(EmailTrigger::cases())->mapWithKeys(
                                    fn (EmailTrigger $trigger): array => [$trigger->value => $trigger->label()],
                                )->all())
                                ->searchable()
                                ->native(false),
                            Toggle::make('is_active')
                                ->label('Active')
                                ->default(true)
                                ->columnSpanFull(),
                        ]),
                    ]),
                Section::make('Content')
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('subject')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        RichEditor::make('html_body')
                            ->label('HTML body')
                            ->required()
                            ->columnSpanFull(),
                        Textarea::make('plain_text_body')
                            ->label('Plain text body')
                            ->rows(8)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
