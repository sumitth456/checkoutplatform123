<?php

namespace App\Filament\Admin\Resources\Products\Schemas;

use App\Models\Product;
use App\Models\SubscriptionPlan;
use App\Modules\Product\Enums\BillingInterval;
use App\Modules\Product\Enums\ProductStatus;
use App\Modules\Product\Enums\ProductType;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Product details')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('name')
                                ->required()
                                ->maxLength(255)
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (Set $set, ?string $state, ?Product $record): void {
                                    if ($record !== null) {
                                        return;
                                    }

                                    $set('slug', Str::slug($state ?? ''));
                                }),
                            TextInput::make('slug')
                                ->required()
                                ->maxLength(255)
                                ->unique(ignoreRecord: true),
                            TextInput::make('sku')
                                ->label('SKU')
                                ->maxLength(255)
                                ->unique(ignoreRecord: true),
                            Select::make('status')
                                ->options(collect(ProductStatus::cases())->mapWithKeys(
                                    fn (ProductStatus $status): array => [$status->value => str($status->name)->headline()]
                                )->all())
                                ->required()
                                ->default(ProductStatus::Draft->value),
                            Select::make('type')
                                ->options(collect(ProductType::cases())->mapWithKeys(
                                    fn (ProductType $type): array => [$type->value => str($type->name)->headline()]
                                )->all())
                                ->required()
                                ->default(ProductType::Digital->value)
                                ->live(),
                            TextInput::make('currency')
                                ->required()
                                ->default('USD')
                                ->maxLength(3),
                        ]),
                        Textarea::make('short_description')
                            ->rows(2)
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->rows(4)
                            ->columnSpanFull(),
                        FileUpload::make('images')
                            ->label('Product images')
                            ->image()
                            ->multiple()
                            ->reorderable()
                            ->directory('products')
                            ->disk('public')
                            ->columnSpanFull(),
                    ]),
                Section::make('Pricing')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('base_price')
                                ->numeric()
                                ->minValue(0)
                                ->prefix('$'),
                            TextInput::make('compare_at_price')
                                ->label('Compare at price')
                                ->numeric()
                                ->minValue(0)
                                ->prefix('$'),
                            Toggle::make('is_taxable')
                                ->default(true),
                            Toggle::make('requires_shipping')
                                ->default(false),
                        ]),
                    ]),
                Section::make('Subscription')
                    ->columnSpanFull()
                    ->visible(fn (Get $get): bool => in_array($get('type'), [ProductType::Subscription->value, ProductType::Digital->value], true))
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('subscription_plan_id')
                                ->label('Subscription plan')
                                ->relationship(
                                    name: 'subscriptionPlan',
                                    titleAttribute: 'name',
                                    modifyQueryUsing: fn ($query) => $query->where('is_active', true),
                                )
                                ->searchable()
                                ->preload()
                                ->nullable(),
                            Select::make('billing_interval')
                                ->options(collect(BillingInterval::cases())->mapWithKeys(
                                    fn (BillingInterval $interval): array => [$interval->value => str($interval->name)->headline()]
                                )->all())
                                ->visible(fn (Get $get): bool => $get('type') === ProductType::Subscription->value),
                            TextInput::make('billing_interval_count')
                                ->numeric()
                                ->minValue(1)
                                ->default(1)
                                ->visible(fn (Get $get): bool => $get('type') === ProductType::Subscription->value),
                            TextInput::make('trial_days')
                                ->numeric()
                                ->minValue(0)
                                ->nullable()
                                ->visible(fn (Get $get): bool => $get('type') === ProductType::Subscription->value),
                        ]),
                    ]),
                Section::make('Publishing')
                    ->columnSpanFull()
                    ->schema([
                        DateTimePicker::make('published_at'),
                    ]),
            ]);
    }
}
