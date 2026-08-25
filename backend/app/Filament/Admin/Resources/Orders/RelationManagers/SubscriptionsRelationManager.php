<?php

namespace App\Filament\Admin\Resources\Orders\RelationManagers;

use App\Filament\Admin\Resources\Subscriptions\SubscriptionResource;
use App\Models\Subscription;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class SubscriptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'subscriptions';

    protected static ?string $title = 'Subscriptions';

    protected static bool $shouldSkipAuthorization = true;

    public function form(Schema $schema): Schema
    {
        return $schema;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['plan', 'product']))
            ->columns([
                TextColumn::make('uuid')
                    ->label('ID')
                    ->limit(8)
                    ->copyable(),
                TextColumn::make('plan.name')
                    ->label('Plan')
                    ->placeholder('—'),
                TextColumn::make('product.name')
                    ->label('Product')
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('amount')
                    ->money(fn (Subscription $record) => $record->currency)
                    ->sortable(),
                TextColumn::make('current_cycle_step')
                    ->label('Billing step')
                    ->sortable(),
                TextColumn::make('next_billing_at')
                    ->label('Next billing date')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('started_at')
                    ->dateTime()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make()
                    ->url(fn (Subscription $record): string => SubscriptionResource::getUrl('view', ['record' => $record])),
            ])
            ->headerActions([])
            ->toolbarActions([])
            ->paginated(false);
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return (string) $ownerRecord->subscriptions()->count();
    }
}
