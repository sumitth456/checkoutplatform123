<?php

namespace App\Filament\Admin\Widgets\Affiliate;

use App\Filament\Admin\Resources\Affiliates\AffiliateResource;
use App\Models\Affiliate;
use App\Support\Admin\AdminAuthorization;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Number;

class TopAffiliatesWidget extends TableWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    protected static ?string $heading = 'Top affiliates';

    protected ?string $pollingInterval = '120s';

    public static function canView(): bool
    {
        return AdminAuthorization::can(auth()->user(), 'affiliate_dashboard.view');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Affiliate::query()
                    ->orderByDesc('lifetime_revenue')
                    ->limit(10),
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->url(fn (Affiliate $record): string => AffiliateResource::getUrl('view', ['record' => $record])),
                Tables\Columns\TextColumn::make('total_conversions')
                    ->label('Conversions')
                    ->numeric(),
                Tables\Columns\TextColumn::make('lifetime_revenue')
                    ->label('Revenue')
                    ->formatStateUsing(fn ($state): string => Number::currency((float) $state, 'USD')),
                Tables\Columns\TextColumn::make('pending_commission')
                    ->label('Pending')
                    ->formatStateUsing(fn ($state): string => Number::currency((float) $state, 'USD')),
            ])
            ->paginated(false);
    }
}
