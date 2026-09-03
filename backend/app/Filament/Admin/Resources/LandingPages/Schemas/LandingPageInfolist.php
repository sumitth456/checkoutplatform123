<?php

namespace App\Filament\Admin\Resources\LandingPages\Schemas;

use App\Filament\Admin\Support\Infolists\AdminInfolistComponents;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class LandingPageInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                AdminInfolistComponents::summaryHeader(),
                AdminInfolistComponents::activityTimeline('Landing page timeline'),
                AdminInfolistComponents::detailsSection('Page details', [
                    Grid::make(3)->schema([
                        TextEntry::make('name'),
                        TextEntry::make('slug')->copyable(),
                        TextEntry::make('is_tshirt_campaign')->label('T-shirt page')->formatStateUsing(fn (bool $state): string => $state ? 'Yes' : 'No'),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('campaign.name')->label('Campaign')->placeholder('—'),
                        TextEntry::make('product.name')->label('Product')->placeholder('—'),
                        TextEntry::make('variant.name')->label('Variant')->placeholder('—'),
                        TextEntry::make('mapped_sku')
                            ->label('SKU')
                            ->state(fn ($record): string => (string) ($record->variant?->sku ?? $record->product?->sku ?? '—')),
                        TextEntry::make('global_domain')
                            ->label('Global domain')
                            ->state(fn (): string => app(\App\Modules\LandingPage\Services\TshirtLandingPageSettingsService::class)->configuredDomain() ?? '—'),
                        TextEntry::make('publicUrl')
                            ->label('Public URL')
                            ->state(fn ($record): string => $record->publicUrl())
                            ->url(fn ($record): string => $record->publicUrl(), true)
                            ->copyable(),
                        TextEntry::make('external_hosting_url')->label('External hosting URL')->placeholder('—')->columnSpanFull(),
                        TextEntry::make('external_hosting_provider')->label('Hosting provider')->placeholder('—'),
                        TextEntry::make('external_hosting_target')->label('Hosting target')->placeholder('—'),
                        TextEntry::make('hosting_status')->label('Hosting status')->placeholder('—'),
                        TextEntry::make('published_at')->dateTime()->placeholder('—'),
                        TextEntry::make('conversion_count')->label('Conversions')->numeric(),
                        TextEntry::make('revenue_total')->label('Revenue')->money('USD'),
                        TextEntry::make('conversion_rate')
                            ->label('Conversion rate')
                            ->state(fn ($record): string => $record->conversionRate().'%'),
                        TextEntry::make('seo_title')->placeholder('—'),
                        TextEntry::make('meta_description')->placeholder('—')->columnSpanFull(),
                    ]),
                ]),
            ]);
    }
}
