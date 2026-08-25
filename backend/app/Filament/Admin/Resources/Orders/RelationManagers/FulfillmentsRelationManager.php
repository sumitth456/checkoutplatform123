<?php

namespace App\Filament\Admin\Resources\Orders\RelationManagers;

use App\Filament\Admin\Resources\DelayFulfillment\DelayFulfillmentResource;
use App\Models\Fulfillment;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class FulfillmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'fulfillments';

    protected static ?string $title = 'Shipping & fulfillment';

    protected static bool $shouldSkipAuthorization = true;

    public function form(Schema $schema): Schema
    {
        return $schema;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('scheduled_ship_date')
                    ->date()
                    ->placeholder('—'),
                TextColumn::make('shipped_at')
                    ->dateTime()
                    ->placeholder('—'),
                TextColumn::make('carrier')
                    ->placeholder('—'),
                TextColumn::make('tracking_number')
                    ->copyable()
                    ->placeholder('—'),
                TextColumn::make('shipment_status')
                    ->badge()
                    ->placeholder('—'),
                TextColumn::make('delivery_status')
                    ->badge()
                    ->placeholder('—'),
                TextColumn::make('delay_days')
                    ->numeric(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make()
                    ->url(fn (Fulfillment $record): string => DelayFulfillmentResource::getUrl('view', ['record' => $record])),
            ])
            ->headerActions([])
            ->toolbarActions([]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return (string) $ownerRecord->fulfillments()->count();
    }
}
