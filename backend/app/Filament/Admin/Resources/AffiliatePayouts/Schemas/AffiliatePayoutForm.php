<?php

namespace App\Filament\Admin\Resources\AffiliatePayouts\Schemas;

use App\Models\Affiliate;
use App\Modules\Affiliate\Enums\AffiliatePaymentMethod;
use App\Modules\Affiliate\Enums\PayoutStatus;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AffiliatePayoutForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Payout details')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('affiliate_id')
                                ->label('Affiliate')
                                ->options(fn (): array => Affiliate::query()->orderBy('name')->pluck('name', 'id')->all())
                                ->searchable()
                                ->preload()
                                ->required(),
                            Select::make('status')
                                ->options(collect(PayoutStatus::cases())->mapWithKeys(
                                    fn (PayoutStatus $status): array => [$status->value => str($status->name)->headline()]
                                )->all())
                                ->required()
                                ->default(PayoutStatus::Pending->value)
                                ->native(false),
                            TextInput::make('amount')
                                ->numeric()
                                ->required()
                                ->prefix('$')
                                ->step(0.01),
                            TextInput::make('currency')
                                ->default('USD')
                                ->maxLength(8),
                            Select::make('payment_method')
                                ->options(collect(AffiliatePaymentMethod::cases())->mapWithKeys(
                                    fn (AffiliatePaymentMethod $method): array => [$method->value => str($method->name)->headline()]
                                )->all())
                                ->native(false),
                            TextInput::make('reference')
                                ->maxLength(255),
                        ]),
                        Textarea::make('notes')
                            ->rows(3)
                            ->columnSpanFull(),
                        KeyValue::make('metadata')->columnSpanFull(),
                    ]),
            ]);
    }
}
