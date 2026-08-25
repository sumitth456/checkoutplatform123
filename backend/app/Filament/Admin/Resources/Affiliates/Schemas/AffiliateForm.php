<?php

namespace App\Filament\Admin\Resources\Affiliates\Schemas;

use App\Modules\Affiliate\Enums\AffiliatePaymentMethod;
use App\Modules\Affiliate\Enums\AffiliateStatus;
use App\Modules\Affiliate\Enums\CommissionType;
use App\Modules\Affiliate\Enums\RecurringCommissionMode;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class AffiliateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Profile')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('name')
                                ->required()
                                ->maxLength(255),
                            TextInput::make('company')
                                ->maxLength(255),
                            TextInput::make('username')
                                ->required()
                                ->unique(ignoreRecord: true)
                                ->maxLength(255),
                            TextInput::make('email')
                                ->email()
                                ->required()
                                ->unique(ignoreRecord: true)
                                ->maxLength(255),
                            TextInput::make('phone')
                                ->tel()
                                ->maxLength(32),
                            TextInput::make('country_code')
                                ->label('Country code')
                                ->maxLength(8),
                            Select::make('status')
                                ->options(collect(AffiliateStatus::cases())->mapWithKeys(
                                    fn (AffiliateStatus $status): array => [$status->value => str($status->name)->headline()]
                                )->all())
                                ->required()
                                ->default(AffiliateStatus::Pending->value)
                                ->native(false),
                            TextInput::make('password')
                                ->password()
                                ->revealable()
                                ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? Hash::make($state) : null)
                                ->dehydrated(fn (?string $state): bool => filled($state))
                                ->required(fn (string $operation): bool => $operation === 'create')
                                ->maxLength(255),
                        ]),
                        Textarea::make('notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
                Section::make('Commission defaults')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(3)->schema([
                            Select::make('default_commission_type')
                                ->label('Commission type')
                                ->options(collect(CommissionType::cases())->mapWithKeys(
                                    fn (CommissionType $type): array => [$type->value => str($type->name)->headline()]
                                )->all())
                                ->required()
                                ->native(false),
                            TextInput::make('default_commission_value')
                                ->label('Commission value')
                                ->numeric()
                                ->required()
                                ->step(0.0001),
                            Select::make('recurring_commission_mode')
                                ->options(collect(RecurringCommissionMode::cases())->mapWithKeys(
                                    fn (RecurringCommissionMode $mode): array => [$mode->value => str($mode->name)->headline()]
                                )->all())
                                ->required()
                                ->native(false),
                            TextInput::make('recurring_commission_rebill_limit')
                                ->label('Rebill limit')
                                ->numeric()
                                ->minValue(1),
                            TextInput::make('cookie_duration_days')
                                ->label('Cookie duration (days)')
                                ->numeric()
                                ->required()
                                ->minValue(1),
                        ]),
                    ]),
                Section::make('Payment & tracking')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(3)->schema([
                            Select::make('payment_method')
                                ->options(collect(AffiliatePaymentMethod::cases())->mapWithKeys(
                                    fn (AffiliatePaymentMethod $method): array => [$method->value => str($method->name)->headline()]
                                )->all())
                                ->native(false),
                            TextInput::make('postback_url')
                                ->label('Postback URL')
                                ->url()
                                ->maxLength(255),
                            TextInput::make('tracking_id')
                                ->label('Tracking ID')
                                ->disabled()
                                ->dehydrated(false)
                                ->visibleOn('edit'),
                            TextInput::make('api_key')
                                ->label('API key')
                                ->disabled()
                                ->dehydrated(false)
                                ->visibleOn('edit'),
                        ]),
                        Grid::make(2)->schema([
                            Toggle::make('allow_coupon_attribution')
                                ->label('Allow coupon attribution')
                                ->default(true),
                            Toggle::make('allow_self_referrals')
                                ->label('Allow self-referrals')
                                ->default(false),
                        ]),
                    ]),
                Section::make('Offers & address')
                    ->columnSpanFull()
                    ->schema([
                        Select::make('offers')
                            ->relationship('offers', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable(),
                        KeyValue::make('address')
                            ->keyLabel('Field')
                            ->valueLabel('Value')
                            ->columnSpanFull(),
                        KeyValue::make('tax_information')
                            ->label('Tax information')
                            ->keyLabel('Field')
                            ->valueLabel('Value')
                            ->columnSpanFull(),
                        KeyValue::make('metadata')
                            ->keyLabel('Key')
                            ->valueLabel('Value')
                            ->columnSpanFull(),
                    ]),
                Section::make('Read-only metrics')
                    ->columnSpanFull()
                    ->visibleOn('edit')
                    ->schema([
                        Grid::make(4)->schema([
                            TextInput::make('pending_commission')
                                ->numeric()
                                ->disabled()
                                ->dehydrated(false),
                            TextInput::make('paid_commission')
                                ->numeric()
                                ->disabled()
                                ->dehydrated(false),
                            TextInput::make('lifetime_revenue')
                                ->numeric()
                                ->disabled()
                                ->dehydrated(false),
                            TextInput::make('total_clicks')
                                ->numeric()
                                ->disabled()
                                ->dehydrated(false),
                            TextInput::make('total_conversions')
                                ->numeric()
                                ->disabled()
                                ->dehydrated(false),
                            DateTimePicker::make('approved_at')
                                ->disabled()
                                ->dehydrated(false),
                            DateTimePicker::make('last_login_at')
                                ->disabled()
                                ->dehydrated(false),
                        ]),
                    ]),
            ]);
    }
}
