<?php

namespace App\Filament\Affiliate\Resources\PortalConversions;

use App\Filament\Affiliate\Concerns\ScopesToAuthenticatedAffiliate;
use App\Filament\Affiliate\Resources\PortalConversions\Pages\ListPortalConversions;
use App\Models\AffiliateConversion;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PortalConversionResource extends Resource
{
    use ScopesToAuthenticatedAffiliate;

    protected static ?string $model = AffiliateConversion::class;

    protected static ?string $navigationLabel = 'Conversions';

    protected static ?string $slug = 'conversions';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static ?int $navigationSort = 3;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('conversion_type')
                    ->badge()
                    ->sortable(),
                TextColumn::make('order.order_number')
                    ->label('Order')
                    ->placeholder('—'),
                TextColumn::make('revenue')
                    ->money('USD')
                    ->sortable(),
                TextColumn::make('commission_amount')
                    ->label('Commission')
                    ->money('USD')
                    ->sortable(),
                TextColumn::make('rebill_number')
                    ->label('Rebill #')
                    ->placeholder('—'),
                TextColumn::make('converted_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('converted_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPortalConversions::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
