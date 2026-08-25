<?php

namespace App\Filament\Affiliate\Resources\PortalPromoMaterials;

use App\Filament\Affiliate\Resources\PortalPromoMaterials\Pages\ListPortalPromoMaterials;
use App\Models\AffiliatePromoMaterial;
use App\Modules\Affiliate\Enums\PromoMaterialType;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PortalPromoMaterialResource extends Resource
{
    protected static ?string $model = AffiliatePromoMaterial::class;

    protected static ?string $navigationLabel = 'Promo Materials';

    protected static ?string $slug = 'promo-materials';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?int $navigationSort = 6;

    public static function getEloquentQuery(): Builder
    {
        /** @var \App\Models\Affiliate $affiliate */
        $affiliate = auth('affiliate')->user();

        return parent::getEloquentQuery()
            ->where('is_active', true)
            ->where(function (Builder $query) use ($affiliate): void {
                $query
                    ->whereNull('allowed_affiliate_ids')
                    ->orWhereJsonContains('allowed_affiliate_ids', $affiliate->id);
            });
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (PromoMaterialType|string $state): string => $state instanceof PromoMaterialType ? str($state->name)->headline() : $state),
                TextColumn::make('offer.name')
                    ->label('Offer')
                    ->placeholder('—'),
                TextColumn::make('description')
                    ->limit(40)
                    ->toggleable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->recordActions([
                Action::make('download')
                    ->label('Download')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->url(fn (AffiliatePromoMaterial $record): ?string => $record->file_url)
                    ->openUrlInNewTab()
                    ->visible(fn (AffiliatePromoMaterial $record): bool => filled($record->file_url)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPortalPromoMaterials::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
