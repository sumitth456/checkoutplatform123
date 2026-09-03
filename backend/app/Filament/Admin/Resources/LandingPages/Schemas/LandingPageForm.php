<?php

namespace App\Filament\Admin\Resources\LandingPages\Schemas;

use App\Models\Product;
use App\Models\Variant;
use App\Modules\LandingPage\Enums\LandingPageStatus;
use App\Modules\LandingPage\Rules\TshirtLandingPageSlugRule;
use App\Modules\LandingPage\Services\TshirtLandingPageSettingsService;
use App\Modules\LandingPage\Services\TshirtLandingPageUrlGenerator;
use App\Modules\LandingPage\Support\TshirtLandingPageSlugValidator;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class LandingPageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Section::make('T-shirt campaign')->columnSpanFull()->schema([
                Grid::make(2)->schema([
                    Toggle::make('is_tshirt_campaign')
                        ->label('T-shirt landing page')
                        ->helperText('Uses the global T-shirt domain and slug-based subdomain URL.')
                        ->live(),
                    Placeholder::make('tshirt_public_url')
                        ->label('Public URL')
                        ->visible(fn (Get $get): bool => (bool) $get('is_tshirt_campaign'))
                        ->content(function (Get $get, ?\App\Models\LandingPage $record): HtmlString {
                            $slug = TshirtLandingPageSlugValidator::normalize(
                                (string) ($get('slug') ?? $record?->slug ?? ''),
                            );
                            $url = app(TshirtLandingPageUrlGenerator::class)->forSlug($slug)
                                ?? 'Configure the global T-shirt domain first.';

                            return new HtmlString('<span class="font-medium text-primary-600">'.e($url).'</span>');
                        }),
                    TextInput::make('name')->required()->maxLength(255)->live(onBlur: true),
                    TextInput::make('slug')
                        ->required()
                        ->maxLength(120)
                        ->unique(ignoreRecord: true)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (?string $state, callable $set): void {
                            if ($state === null) {
                                return;
                            }

                            $set('slug', TshirtLandingPageSlugValidator::normalize($state));
                        })
                        ->rules(fn (Get $get): array => ($get('is_tshirt_campaign') ? [new TshirtLandingPageSlugRule()] : [])),
                    Select::make('status')
                        ->options(collect(LandingPageStatus::cases())->mapWithKeys(
                            fn (LandingPageStatus $s): array => [$s->value => $s->label()]
                        )->all())
                        ->required()
                        ->native(false),
                    Select::make('campaign_id')
                        ->label('Campaign')
                        ->relationship('campaign', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),
                    Select::make('product_id')
                        ->label('Product')
                        ->relationship('product', 'name')
                        ->searchable()
                        ->preload()
                        ->visible(fn (Get $get): bool => (bool) $get('is_tshirt_campaign'))
                        ->live(),
                    Select::make('variant_id')
                        ->label('Variant')
                        ->options(function (Get $get): array {
                            $productId = $get('product_id');

                            if (blank($productId)) {
                                return [];
                            }

                            return Variant::query()
                                ->where('product_id', $productId)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all();
                        })
                        ->searchable()
                        ->visible(fn (Get $get): bool => (bool) $get('is_tshirt_campaign'))
                        ->live(),
                    Placeholder::make('mapped_sku')
                        ->label('SKU')
                        ->visible(fn (Get $get): bool => (bool) $get('is_tshirt_campaign'))
                        ->content(function (Get $get): string {
                            $variantId = $get('variant_id');
                            $productId = $get('product_id');

                            if (filled($variantId)) {
                                return (string) (Variant::query()->find($variantId)?->sku ?? '—');
                            }

                            if (filled($productId)) {
                                return (string) (Product::query()->find($productId)?->sku ?? '—');
                            }

                            return '—';
                        }),
                    Select::make('template_id')
                        ->label('Template')
                        ->relationship('template', 'name')
                        ->searchable()
                        ->preload(),
                ]),
            ]),
            Section::make('External hosting')
                ->description('Optional — when the landing page HTML is hosted outside CheckoutPlatform.')
                ->columnSpanFull()
                ->visible(fn (Get $get): bool => (bool) $get('is_tshirt_campaign'))
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('external_hosting_provider')
                            ->label('Hosting provider')
                            ->maxLength(255),
                        TextInput::make('external_hosting_url')
                            ->label('External landing page URL')
                            ->url()
                            ->maxLength(2048),
                        TextInput::make('external_hosting_target')
                            ->label('External hosting target')
                            ->helperText('DNS/hosting target used by your external platform (for documentation only).')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('hosting_status')
                            ->label('Hosting status')
                            ->maxLength(100),
                    ]),
                ]),
            Section::make('Global domain')
                ->columnSpanFull()
                ->visible(fn (Get $get): bool => (bool) $get('is_tshirt_campaign'))
                ->schema([
                    Placeholder::make('global_tshirt_domain')
                        ->label('T-shirt domain')
                        ->content(fn (): string => app(TshirtLandingPageSettingsService::class)->configuredDomain() ?? 'Not configured'),
                    Placeholder::make('global_wildcard')
                        ->label('Wildcard DNS')
                        ->content(fn (): string => app(TshirtLandingPageSettingsService::class)->wildcardHostLabel()),
                ]),
            Section::make('SEO')->columnSpanFull()->schema([
                Grid::make(2)->schema([
                    TextInput::make('seo_title')->maxLength(255),
                    FileUpload::make('og_image_path')
                        ->label('OG image')
                        ->image()
                        ->directory('landing-pages/og')
                        ->visibility('public'),
                ]),
                Textarea::make('meta_description')->rows(3)->columnSpanFull(),
            ]),
            Section::make('Page builder')
                ->description('Use the Monaco builder for HTML, CSS, JavaScript, live preview, and auto-save.')
                ->columnSpanFull()
                ->schema([]),
        ]);
    }
}
