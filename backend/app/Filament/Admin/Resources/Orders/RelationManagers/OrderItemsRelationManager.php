<?php

namespace App\Filament\Admin\Resources\Orders\RelationManagers;

use App\Models\OrderItem;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class OrderItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Line items';

    protected static bool $shouldSkipAuthorization = true;

    public function form(Schema $schema): Schema
    {
        return $schema;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('sku')
                    ->label('SKU'),
                TextColumn::make('product.name')
                    ->label('Product')
                    ->placeholder('—'),
                TextColumn::make('variant.sku')
                    ->label('Variant SKU')
                    ->placeholder('—'),
                TextColumn::make('quantity')
                    ->numeric(),
                TextColumn::make('unit_price')
                    ->money(fn (OrderItem $record) => $record->order?->currency ?? 'USD'),
                TextColumn::make('total')
                    ->money(fn (OrderItem $record) => $record->order?->currency ?? 'USD'),
                IconColumn::make('is_subscription')
                    ->boolean(),
                TextColumn::make('next_billing_at')
                    ->label('Next billing')
                    ->state(function (OrderItem $record): ?\Illuminate\Support\Carbon {
                        if (! $record->is_subscription) {
                            return null;
                        }

                        $subscription = $record->order?->subscriptions()
                            ->when(
                                $record->id,
                                fn ($query) => $query->where(function ($query) use ($record): void {
                                    $query->where('order_item_id', $record->id)
                                        ->orWhereNull('order_item_id');
                                }),
                            )
                            ->orderByDesc('id')
                            ->first();

                        return $subscription?->next_billing_at;
                    })
                    ->dateTime()
                    ->placeholder('—'),
            ])
            ->paginated(false)
            ->recordActions([])
            ->headerActions([])
            ->toolbarActions([]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return (string) $ownerRecord->items()->count();
    }
}
