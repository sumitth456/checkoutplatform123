<?php

namespace App\Filament\Affiliate\Resources\PortalCommissions;

use App\Filament\Affiliate\Concerns\ScopesToAuthenticatedAffiliate;
use App\Filament\Affiliate\Resources\PortalCommissions\Pages\ListPortalCommissions;
use App\Models\AffiliateCommission;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PortalCommissionResource extends Resource
{
    use ScopesToAuthenticatedAffiliate;

    protected static ?string $model = AffiliateCommission::class;

    protected static ?string $navigationLabel = 'Commissions';

    protected static ?string $slug = 'commissions';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = 4;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('rule_source')
                    ->label('Rule')
                    ->toggleable(),
                TextColumn::make('order_amount')
                    ->money('USD')
                    ->sortable(),
                TextColumn::make('commission_amount')
                    ->label('Commission')
                    ->money('USD')
                    ->sortable(),
                TextColumn::make('rebill_number')
                    ->label('Rebill #')
                    ->placeholder('—'),
                TextColumn::make('earned_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('earned_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPortalCommissions::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
