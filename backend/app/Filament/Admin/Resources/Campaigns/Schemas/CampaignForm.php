<?php

namespace App\Filament\Admin\Resources\Campaigns\Schemas;

use App\Modules\Campaign\Enums\CampaignStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CampaignForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('General')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('public_id')
                                ->label('Campaign ID')
                                ->disabled()
                                ->dehydrated(false)
                                ->visibleOn(['edit', 'view']),
                            TextInput::make('name')
                                ->required()
                                ->maxLength(255),
                            Select::make('status')
                                ->options(collect(CampaignStatus::cases())->mapWithKeys(
                                    fn (CampaignStatus $status): array => [$status->value => str($status->name)->headline()]
                                )->all())
                                ->required()
                                ->native(false),
                            Select::make('brand_id')
                                ->label('Brand')
                                ->relationship('brand', 'name')
                                ->searchable()
                                ->preload(),
                            Select::make('subscription_plan_id')
                                ->label('Subscription plan')
                                ->relationship(
                                    'subscriptionPlan',
                                    'name',
                                    fn ($query) => $query->where('is_active', true)->orderBy('name'),
                                )
                                ->searchable()
                                ->preload()
                                ->nullable()
                                ->helperText('When set, all products in this campaign inherit rebill schedule, retry rules, and gateway settings from the plan. You only configure bill product/SKU per product.'),
                            Select::make('landing_page_id')
                                ->label('Landing page')
                                ->relationship(
                                    'landingPage',
                                    'name',
                                    fn ($query) => $query->orderBy('name'),
                                )
                                ->searchable()
                                ->preload()
                                ->nullable()
                                ->helperText('Used when opening /checkout/{campaign}. One page can serve multiple campaigns.'),
                            TextInput::make('store_name')
                                ->label('Store')
                                ->maxLength(255),
                            TextInput::make('store_code')
                                ->label('Store code')
                                ->maxLength(64),
                            TextInput::make('currency')
                                ->maxLength(3)
                                ->default('USD')
                                ->required(),
                            TextInput::make('timezone')
                                ->default('UTC')
                                ->required()
                                ->helperText('IANA timezone, e.g. America/New_York'),
                        ]),
                        Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull(),
                        Textarea::make('internal_notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
