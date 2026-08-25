<?php



namespace App\Filament\Admin\Resources\CheckoutPaymentFailures\Schemas;



use Filament\Infolists\Components\TextEntry;

use Filament\Schemas\Components\Grid;

use Filament\Schemas\Components\Section;

use Filament\Schemas\Schema;



class CheckoutPaymentFailureInfolist

{

    public static function configure(Schema $schema): Schema

    {

        return $schema

            ->columns(2)

            ->components([

                Section::make('Failure')

                    ->columnSpanFull()

                    ->schema([

                        Grid::make(3)->schema([

                            TextEntry::make('uuid')->copyable(),

                            TextEntry::make('created_at')->dateTime(),

                            TextEntry::make('provider')->badge(),

                            TextEntry::make('customer_email')->placeholder('—'),

                            TextEntry::make('amount')

                                ->money(fn ($record) => $record->currency ?? 'USD')

                                ->placeholder('—'),

                            TextEntry::make('ip_address')->placeholder('—'),

                            TextEntry::make('failure_code')

                                ->badge()

                                ->color('danger')

                                ->placeholder('—'),

                            TextEntry::make('payment_intent_id')->placeholder('—'),

                            TextEntry::make('idempotency_key')->placeholder('—'),

                            TextEntry::make('failure_message')

                                ->columnSpanFull()

                                ->placeholder('—'),

                        ]),

                    ]),

                Section::make('Request payload')

                    ->columnSpanFull()

                    ->schema([

                        TextEntry::make('request_payload')

                            ->formatStateUsing(fn (?array $state): string => self::formatJson($state))

                            ->prose()

                            ->columnSpanFull(),

                    ]),

                Section::make('Gateway response')

                    ->columnSpanFull()

                    ->schema([

                        TextEntry::make('gateway_response')

                            ->formatStateUsing(fn (?array $state): string => self::formatJson($state))

                            ->prose()

                            ->columnSpanFull(),

                    ]),

            ]);

    }



    private static function formatJson(?array $state): string

    {

        if ($state === null || $state === []) {

            return '—';

        }



        return json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '—';

    }

}

