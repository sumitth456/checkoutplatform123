<?php

namespace App\Filament\Admin\Resources\AffiliateTrackingLinks\Schemas;

use App\Models\Affiliate;
use App\Models\AffiliateOffer;
use App\Models\Campaign;
use App\Models\LandingPage;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AffiliateTrackingLinkForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Link configuration')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('affiliate_id')
                                ->label('Affiliate')
                                ->options(fn (): array => Affiliate::query()->orderBy('name')->pluck('name', 'id')->all())
                                ->searchable()
                                ->preload()
                                ->required()
                                ->live(),
                            TextInput::make('name')
                                ->maxLength(255),
                            Select::make('offer_id')
                                ->label('Offer')
                                ->options(fn (): array => AffiliateOffer::query()->orderBy('name')->pluck('name', 'id')->all())
                                ->searchable()
                                ->preload(),
                            Select::make('platform_campaign_id')
                                ->label('Campaign')
                                ->options(fn (): array => Campaign::query()->orderBy('name')->pluck('name', 'id')->all())
                                ->searchable()
                                ->preload(),
                            Select::make('landing_page_id')
                                ->label('Landing page')
                                ->options(fn (): array => LandingPage::query()->orderBy('name')->pluck('name', 'id')->all())
                                ->searchable()
                                ->preload(),
                            Toggle::make('is_active')
                                ->label('Active')
                                ->default(true),
                        ]),
                        TextInput::make('destination_url')
                            ->label('Destination URL')
                            ->url()
                            ->required()
                            ->columnSpanFull()
                            ->helperText('The generated tracking URL is built automatically on save.'),
                        TextInput::make('generated_url')
                            ->label('Generated URL')
                            ->disabled()
                            ->dehydrated(false)
                            ->visibleOn('edit')
                            ->columnSpanFull(),
                        TextInput::make('code')
                            ->label('Link code')
                            ->disabled()
                            ->dehydrated(false)
                            ->visibleOn('edit'),
                        KeyValue::make('default_sub_ids')
                            ->label('Default sub IDs')
                            ->keyLabel('Parameter')
                            ->valueLabel('Value')
                            ->columnSpanFull(),
                        KeyValue::make('metadata')->columnSpanFull(),
                    ]),
            ]);
    }
}
