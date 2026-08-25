<?php

namespace App\Filament\Affiliate\Resources\PortalClicks;

use App\Filament\Affiliate\Concerns\ScopesToAuthenticatedAffiliate;
use App\Filament\Affiliate\Resources\PortalClicks\Pages\ListPortalClicks;
use App\Models\AffiliateClick;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PortalClickResource extends Resource
{
    use ScopesToAuthenticatedAffiliate;

    protected static ?string $model = AffiliateClick::class;

    protected static ?string $navigationLabel = 'Clicks';

    protected static ?string $slug = 'clicks';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCursorArrowRays;

    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('click_id')
                    ->label('Click ID')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('offer.name')
                    ->label('Offer')
                    ->placeholder('—'),
                TextColumn::make('country_code')
                    ->label('Country')
                    ->toggleable(),
                IconColumn::make('is_unique')
                    ->boolean()
                    ->label('Unique'),
                TextColumn::make('clicked_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('clicked_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPortalClicks::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
