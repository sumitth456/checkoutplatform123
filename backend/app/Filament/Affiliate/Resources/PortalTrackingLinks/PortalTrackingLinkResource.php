<?php

namespace App\Filament\Affiliate\Resources\PortalTrackingLinks;

use App\Filament\Affiliate\Concerns\ScopesToAuthenticatedAffiliate;
use App\Filament\Affiliate\Resources\PortalTrackingLinks\Pages\ListPortalTrackingLinks;
use App\Models\AffiliateTrackingLink;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PortalTrackingLinkResource extends Resource
{
    use ScopesToAuthenticatedAffiliate;

    protected static ?string $model = AffiliateTrackingLink::class;

    protected static ?string $navigationLabel = 'Tracking Links';

    protected static ?string $slug = 'tracking-links';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static ?int $navigationSort = 5;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('generated_url')
                    ->label('Tracking URL')
                    ->copyable()
                    ->wrap(),
                TextColumn::make('click_count')
                    ->label('Clicks')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('conversion_count')
                    ->label('Conversions')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->boolean(),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPortalTrackingLinks::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
