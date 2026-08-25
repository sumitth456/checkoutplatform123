<?php

namespace App\Filament\Admin\Resources\AffiliatePromoMaterials\Schemas;

use App\Models\Affiliate;
use App\Models\AffiliateOffer;
use App\Models\Coupon;
use App\Models\LandingPage;
use App\Modules\Affiliate\Enums\PromoMaterialType;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AffiliatePromoMaterialForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Material details')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('name')
                                ->required()
                                ->maxLength(255),
                            Select::make('type')
                                ->options(collect(PromoMaterialType::cases())->mapWithKeys(
                                    fn (PromoMaterialType $type): array => [$type->value => str($type->name)->headline()]
                                )->all())
                                ->required()
                                ->native(false),
                            Toggle::make('is_active')
                                ->label('Active')
                                ->default(true),
                        ]),
                        Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
                Section::make('Assets & associations')
                    ->columnSpanFull()
                    ->schema([
                        FileUpload::make('file_path')
                            ->label('Upload file')
                            ->directory('affiliate-promo')
                            ->visibility('public'),
                        TextInput::make('file_url')
                            ->label('External file URL')
                            ->url()
                            ->maxLength(255),
                        Select::make('offer_id')
                            ->label('Offer')
                            ->options(fn (): array => AffiliateOffer::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->preload(),
                        Select::make('landing_page_id')
                            ->label('Landing page')
                            ->options(fn (): array => LandingPage::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->preload(),
                        Select::make('coupon_id')
                            ->label('Coupon')
                            ->options(fn (): array => Coupon::query()->orderBy('code')->pluck('code', 'id')->all())
                            ->searchable()
                            ->preload(),
                        Select::make('allowed_affiliate_ids')
                            ->label('Allowed affiliates')
                            ->multiple()
                            ->options(fn (): array => Affiliate::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->preload()
                            ->columnSpanFull(),
                        KeyValue::make('metadata')->columnSpanFull(),
                    ]),
            ]);
    }
}
