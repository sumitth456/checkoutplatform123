<?php



namespace App\Filament\Admin\Resources\PaymentGateways\Schemas;



use App\Modules\Payment\Enums\PaymentProvider;

use Filament\Forms\Components\Select;

use Filament\Forms\Components\Textarea;

use Filament\Forms\Components\TextInput;

use Filament\Forms\Components\Toggle;

use Filament\Schemas\Components\Grid;

use Filament\Schemas\Components\Section;

use Filament\Schemas\Components\Utilities\Get;

use Filament\Schemas\Components\Utilities\Set;

use Filament\Schemas\Schema;

use Illuminate\Support\Str;



class PaymentGatewayForm

{

    public static function configure(Schema $schema): Schema

    {

        return $schema

            ->columns(2)

            ->components([

                Section::make('Gateway')

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

                            Select::make('provider')

                                ->options(collect(PaymentProvider::cases())->mapWithKeys(

                                    fn (PaymentProvider $provider): array => [

                                        $provider->value => str($provider->name)->replace('_', ' ')->title(),

                                    ],

                                )->all())

                                ->required()

                                ->native(false)

                                ->live(),

                            Textarea::make('description')

                                ->rows(2)

                                ->columnSpanFull(),

                        ]),

                    ]),

                Section::make('Credentials')

                    ->columnSpanFull()

                    ->description('Stored encrypted. Leave secret fields blank on edit to keep existing values.')

                    ->schema([

                        Grid::make(2)->schema([

                            ...self::stripeCredentialFields(),

                            ...self::paypalCredentialFields(),

                            ...self::authorizeNetCredentialFields(),

                            ...self::nmiCredentialFields(),

                        ]),

                    ]),

                Section::make('Capabilities')

                    ->schema([

                        Toggle::make('is_active')->default(true),

                        Toggle::make('is_default')->label('Default gateway'),

                        Toggle::make('test_mode')->label('Test / sandbox mode'),

                        Toggle::make('supports_subscriptions')->label('Supports subscriptions')->default(true),

                        Toggle::make('supports_refunds')->label('Supports refunds')->default(true),

                        Toggle::make('supports_partial_refunds')->label('Supports partial refunds'),

                    ]),

                Section::make('Routing & limits')

                    ->schema([

                        TextInput::make('sort_order')

                            ->label('Priority (sort order)')

                            ->numeric()

                            ->minValue(0)

                            ->default(100)

                            ->required(),

                        TextInput::make('weight')

                            ->numeric()

                            ->minValue(1)

                            ->default(100)

                            ->required()

                            ->helperText('Load-balancing weight within the same priority tier.'),

                        TextInput::make('daily_limit_amount')

                            ->label('Daily volume limit')

                            ->numeric()

                            ->minValue(0)

                            ->prefix('$'),

                        TextInput::make('monthly_limit_amount')

                            ->label('Monthly volume limit')

                            ->numeric()

                            ->minValue(0)

                            ->prefix('$'),

                        Toggle::make('failover_enabled')

                            ->label('Allow failover')

                            ->default(true)

                            ->columnSpanFull(),

                    ]),

                Section::make('Retry settings')

                    ->schema([

                        TextInput::make('settings.retry.max_attempts')

                            ->label('Max attempts')

                            ->numeric()

                            ->minValue(0)

                            ->default(3),

                        TextInput::make('settings.retry.delay_seconds')

                            ->label('Delay between attempts (seconds)')

                            ->numeric()

                            ->minValue(0)

                            ->default(60),

                    ]),

            ]);

    }



    /**

     * @return list<\Filament\Schemas\Components\Component>

     */

    private static function stripeCredentialFields(): array

    {

        return [

            TextInput::make('credentials.secret_key')

                ->label('Stripe secret key')

                ->password()

                ->revealable()

                ->dehydrated(fn (?string $state): bool => filled($state))

                ->required(fn (Get $get, string $operation): bool => $operation === 'create' && $get('provider') === PaymentProvider::Stripe->value)

                ->visible(fn (Get $get): bool => $get('provider') === PaymentProvider::Stripe->value),

            TextInput::make('credentials.publishable_key')

                ->label('Stripe publishable key')

                ->visible(fn (Get $get): bool => $get('provider') === PaymentProvider::Stripe->value),

            TextInput::make('credentials.webhook_secret')

                ->label('Stripe webhook secret')

                ->password()

                ->revealable()

                ->dehydrated(fn (?string $state): bool => filled($state))

                ->visible(fn (Get $get): bool => $get('provider') === PaymentProvider::Stripe->value),

        ];

    }



    /**

     * @return list<\Filament\Schemas\Components\Component>

     */

    private static function paypalCredentialFields(): array

    {

        return [

            TextInput::make('credentials.client_id')

                ->label('PayPal client ID')

                ->required(fn (Get $get, string $operation): bool => $operation === 'create' && $get('provider') === PaymentProvider::PayPal->value)

                ->visible(fn (Get $get): bool => $get('provider') === PaymentProvider::PayPal->value),

            TextInput::make('credentials.client_secret')

                ->label('PayPal client secret')

                ->password()

                ->revealable()

                ->dehydrated(fn (?string $state): bool => filled($state))

                ->required(fn (Get $get, string $operation): bool => $operation === 'create' && $get('provider') === PaymentProvider::PayPal->value)

                ->visible(fn (Get $get): bool => $get('provider') === PaymentProvider::PayPal->value),

            TextInput::make('credentials.webhook_id')

                ->label('PayPal webhook ID')

                ->visible(fn (Get $get): bool => $get('provider') === PaymentProvider::PayPal->value),

        ];

    }



    /**

     * @return list<\Filament\Schemas\Components\Component>

     */

    private static function authorizeNetCredentialFields(): array

    {

        return [

            TextInput::make('credentials.api_login_id')

                ->label('Authorize.Net API login ID')

                ->required(fn (Get $get, string $operation): bool => $operation === 'create' && $get('provider') === PaymentProvider::AuthorizeNet->value)

                ->visible(fn (Get $get): bool => $get('provider') === PaymentProvider::AuthorizeNet->value),

            TextInput::make('credentials.transaction_key')

                ->label('Authorize.Net transaction key')

                ->password()

                ->revealable()

                ->dehydrated(fn (?string $state): bool => filled($state))

                ->required(fn (Get $get, string $operation): bool => $operation === 'create' && $get('provider') === PaymentProvider::AuthorizeNet->value)

                ->visible(fn (Get $get): bool => $get('provider') === PaymentProvider::AuthorizeNet->value),

        ];

    }



    /**

     * @return list<\Filament\Schemas\Components\Component>

     */

    private static function nmiCredentialFields(): array

    {

        return [

            TextInput::make('credentials.security_key')

                ->label('NMI security key')

                ->password()

                ->revealable()

                ->dehydrated(fn (?string $state): bool => filled($state))

                ->required(fn (Get $get, string $operation): bool => $operation === 'create' && $get('provider') === PaymentProvider::Nmi->value)

                ->visible(fn (Get $get): bool => $get('provider') === PaymentProvider::Nmi->value),

        ];

    }

}

