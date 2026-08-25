<?php

namespace App\Filament\Admin\Resources\AffiliateCampaignMappings\Schemas;

use App\Models\Affiliate;
use App\Models\AffiliateOffer;
use App\Models\Campaign;
use App\Modules\Affiliate\Enums\CommissionType;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AffiliateCampaignMappingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Mapping')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('affiliate_id')
                                ->label('Affiliate')
                                ->options(fn (): array => Affiliate::query()->orderBy('name')->pluck('name', 'id')->all())
                                ->searchable()
                                ->preload()
                                ->required(),
                            Select::make('platform_campaign_id')
                                ->label('Campaign')
                                ->options(fn (): array => Campaign::query()->orderBy('name')->pluck('name', 'id')->all())
                                ->searchable()
                                ->preload()
                                ->required(),
                            Select::make('offer_id')
                                ->label('Offer override')
                                ->options(fn (): array => AffiliateOffer::query()->orderBy('name')->pluck('name', 'id')->all())
                                ->searchable()
                                ->preload(),
                            Toggle::make('is_active')
                                ->label('Active')
                                ->default(true),
                        ]),
                    ]),
                Section::make('Commission override')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('commission_type')
                                ->options(collect(CommissionType::cases())->mapWithKeys(
                                    fn (CommissionType $type): array => [$type->value => str($type->name)->headline()]
                                )->all())
                                ->native(false),
                            TextInput::make('commission_value')
                                ->numeric()
                                ->step(0.0001),
                        ]),
                        KeyValue::make('metadata')->columnSpanFull(),
                    ]),
            ]);
    }
}
