<?php

namespace App\Filament\Affiliate\Resources\PortalPayouts;

use App\Filament\Affiliate\Concerns\ScopesToAuthenticatedAffiliate;
use App\Filament\Affiliate\Resources\PortalPayouts\Pages\ListPortalPayouts;
use App\Models\AffiliatePayout;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PortalPayoutResource extends Resource
{
    use ScopesToAuthenticatedAffiliate;

    protected static ?string $model = AffiliatePayout::class;

    protected static ?string $navigationLabel = 'Payouts';

    protected static ?string $slug = 'payouts';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?int $navigationSort = 7;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('amount')
                    ->money('USD')
                    ->sortable(),
                TextColumn::make('payment_method')
                    ->label('Method')
                    ->toggleable(),
                TextColumn::make('paid_at')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPortalPayouts::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
