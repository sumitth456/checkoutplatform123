<?php



namespace App\Filament\Admin\Resources\StripeWebhookEvents\Schemas;



use App\Modules\StripeWebhook\Enums\StripeWebhookProcessingStatus;

use Filament\Infolists\Components\TextEntry;

use Filament\Schemas\Components\Grid;

use Filament\Schemas\Components\Section;

use Filament\Schemas\Schema;



class StripeWebhookEventInfolist

{

    public static function configure(Schema $schema): Schema

    {

        return $schema

            ->columns(2)

            ->components([

                Section::make('Webhook')

                    ->columnSpanFull()

                    ->schema([

                        Grid::make(3)->schema([

                            TextEntry::make('uuid')->copyable(),

                            TextEntry::make('stripe_event_id')->copyable(),

                            TextEntry::make('event_type'),

                            TextEntry::make('status')

                                ->badge()

                                ->color(fn (StripeWebhookProcessingStatus $state): string => match ($state) {

                                    StripeWebhookProcessingStatus::Processed => 'success',

                                    StripeWebhookProcessingStatus::Failed => 'danger',

                                    StripeWebhookProcessingStatus::Duplicate => 'warning',

                                    StripeWebhookProcessingStatus::Processing => 'info',

                                    default => 'gray',

                                }),

                            TextEntry::make('attempt_count'),

                            TextEntry::make('received_at')->dateTime(),

                            TextEntry::make('processed_at')->dateTime()->placeholder('—'),

                            TextEntry::make('last_error')

                                ->columnSpanFull()

                                ->placeholder('—'),

                        ]),

                    ]),

                Section::make('Payload')

                    ->columnSpanFull()

                    ->schema([

                        TextEntry::make('payload')

                            ->formatStateUsing(fn (?array $state): string => self::formatJson($state))

                            ->prose()

                            ->columnSpanFull(),

                    ]),

                Section::make('Normalized payload')

                    ->columnSpanFull()

                    ->schema([

                        TextEntry::make('normalized_payload')

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

