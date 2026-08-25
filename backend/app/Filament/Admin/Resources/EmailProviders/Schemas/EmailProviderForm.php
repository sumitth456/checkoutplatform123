<?php

namespace App\Filament\Admin\Resources\EmailProviders\Schemas;

use App\Modules\Email\Enums\EmailProviderStatus;
use App\Modules\Email\Enums\EmailProviderType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class EmailProviderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Provider')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('name')
                                ->required()
                                ->maxLength(255),
                            Select::make('provider_type')
                                ->label('Provider type')
                                ->options(collect(EmailProviderType::cases())->mapWithKeys(
                                    fn (EmailProviderType $type): array => [$type->value => $type->label()],
                                )->all())
                                ->required()
                                ->native(false)
                                ->live(),
                            Select::make('status')
                                ->options(collect(EmailProviderStatus::cases())->mapWithKeys(
                                    fn (EmailProviderStatus $status): array => [
                                        $status->value => str($status->name)->headline(),
                                    ],
                                )->all())
                                ->default(EmailProviderStatus::Disabled->value)
                                ->required()
                                ->native(false),
                            TextInput::make('priority')
                                ->numeric()
                                ->minValue(0)
                                ->default(100)
                                ->required(),
                        ]),
                    ]),
                Section::make('API credentials')
                    ->columnSpanFull()
                    ->description('Stored encrypted. Leave blank on edit to keep existing values.')
                    ->visible(fn (Get $get): bool => $get('provider_type') !== EmailProviderType::Smtp->value)
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('api_key')
                                ->label('API key')
                                ->password()
                                ->revealable()
                                ->dehydrated(fn (?string $state): bool => filled($state)),
                            TextInput::make('secret_key')
                                ->label('Secret key')
                                ->password()
                                ->revealable()
                                ->dehydrated(fn (?string $state): bool => filled($state)),
                        ]),
                    ]),
                Section::make('Klaviyo settings')
                    ->columnSpanFull()
                    ->description('Option A — Klaviyo transactional flow: platform fires an event; your Klaviyo flow sends the email. Queue status "sent" only means Klaviyo accepted the event (202).')
                    ->visible(fn (Get $get): bool => $get('provider_type') === EmailProviderType::Klaviyo->value)
                    ->schema([
                        TextInput::make('settings.metric_name')
                            ->label('Event metric name')
                            ->default('Checkout Platform Email')
                            ->helperText('Create a metric-triggered flow in Klaviyo using this exact name. Template subject: {{ event.subject }}, body: {{ event.html_body|safe }}.'),
                    ]),
                Section::make('SMTP relay (optional)')
                    ->columnSpanFull()
                    ->description('Optional direct SMTP delivery (e.g. Gmail). Used by Klaviyo provider before firing events.')
                    ->visible(fn (Get $get): bool => in_array($get('provider_type'), [
                        EmailProviderType::Klaviyo->value,
                        EmailProviderType::Ses->value,
                    ], true))
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('smtp_host')
                                ->label('SMTP host')
                                ->placeholder('smtp.gmail.com')
                                ->maxLength(255),
                            TextInput::make('smtp_port')
                                ->label('SMTP port')
                                ->numeric()
                                ->default(587),
                            Select::make('smtp_encryption')
                                ->label('Encryption')
                                ->options([
                                    'tls' => 'TLS',
                                    'ssl' => 'SSL',
                                    '' => 'None',
                                ])
                                ->default('tls')
                                ->native(false),
                            TextInput::make('username')
                                ->maxLength(255),
                            TextInput::make('password')
                                ->password()
                                ->revealable()
                                ->dehydrated(fn (?string $state): bool => filled($state)),
                        ]),
                    ]),
                Section::make('SMTP settings')
                    ->columnSpanFull()
                    ->description('Use Test connection after saving. Gmail: host smtp.gmail.com, port 587, TLS, and a Google App Password (not your login password). Host and username must match the same mail provider.')
                    ->visible(fn (Get $get): bool => $get('provider_type') === EmailProviderType::Smtp->value)
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('smtp_host')
                                ->label('SMTP host')
                                ->placeholder('smtp.gmail.com')
                                ->maxLength(255)
                                ->required(),
                            TextInput::make('smtp_port')
                                ->label('SMTP port')
                                ->numeric()
                                ->default(587)
                                ->minValue(1)
                                ->maxValue(65535),
                            Select::make('smtp_encryption')
                                ->label('Encryption')
                                ->options([
                                    'tls' => 'TLS',
                                    'ssl' => 'SSL',
                                    '' => 'None',
                                ])
                                ->native(false),
                            TextInput::make('username')
                                ->maxLength(255),
                            TextInput::make('password')
                                ->password()
                                ->revealable()
                                ->dehydrated(fn (?string $state): bool => filled($state)),
                        ]),
                    ]),
                Section::make('From & reply-to')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('from_name')
                                ->label('From name')
                                ->required()
                                ->maxLength(255),
                            TextInput::make('from_email')
                                ->label('From email')
                                ->email()
                                ->required()
                                ->maxLength(255),
                            TextInput::make('reply_to_name')
                                ->label('Reply-to name')
                                ->maxLength(255),
                            TextInput::make('reply_to_email')
                                ->label('Reply-to email')
                                ->email()
                                ->maxLength(255),
                        ]),
                    ]),
                Section::make('Limits & defaults')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('rate_limit_per_minute')
                                ->label('Rate limit (per minute)')
                                ->numeric()
                                ->minValue(0),
                            TextInput::make('daily_limit')
                                ->label('Daily send limit')
                                ->numeric()
                                ->minValue(0),
                            Toggle::make('is_default')
                                ->label('Default provider')
                                ->columnSpanFull(),
                        ]),
                    ]),
            ]);
    }
}
