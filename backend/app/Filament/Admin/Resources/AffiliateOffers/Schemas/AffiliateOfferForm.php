<?php

namespace App\Filament\Admin\Resources\AffiliateOffers\Schemas;

use App\Models\Campaign;
use App\Models\LandingPage;
use App\Models\Product;
use App\Modules\Affiliate\Enums\CommissionType;
use App\Modules\Affiliate\Enums\RecurringCommissionMode;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AffiliateOfferForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Offer details')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('name')
                                ->required()
                                ->maxLength(255),
                            Toggle::make('is_active')
                                ->label('Active')
                                ->default(true),
                        ]),
                        Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
                Section::make('Commission')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(3)->schema([
                            Select::make('commission_type')
                                ->options(collect(CommissionType::cases())->mapWithKeys(
                                    fn (CommissionType $type): array => [$type->value => str($type->name)->headline()]
                                )->all())
                                ->required()
                                ->native(false),
                            TextInput::make('commission_value')
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
                        ]),
                    ]),
                Section::make('Restrictions')
                    ->columnSpanFull()
                    ->schema([
                        Select::make('allowed_campaign_ids')
                            ->label('Allowed campaigns')
                            ->multiple()
                            ->options(fn (): array => Campaign::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->preload(),
                        Select::make('allowed_product_ids')
                            ->label('Allowed products')
                            ->multiple()
                            ->options(fn (): array => Product::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->preload(),
                        Select::make('allowed_landing_page_ids')
                            ->label('Allowed landing pages')
                            ->multiple()
                            ->options(fn (): array => LandingPage::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->preload(),
                        Select::make('affiliates')
                            ->relationship('affiliates', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->columnSpanFull(),
                        KeyValue::make('metadata')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
