<?php

namespace App\Filament\Admin\Resources\Subscriptions\RelationManagers;

use App\Models\BillingLedgerEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class BillingChargesRelationManager extends RelationManager
{
    protected static string $relationship = 'billingLedgerEntries';

    protected static ?string $title = 'Previous charges';

    protected static bool $shouldSkipAuthorization = true;

    public function form(Schema $schema): Schema
    {
        return $schema;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('attempted_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('outcome')
                    ->badge()
                    ->sortable(),
                TextColumn::make('amount')
                    ->money(fn (BillingLedgerEntry $record) => $record->currency),
                TextColumn::make('cycle_step')
                    ->label('Step'),
                TextColumn::make('retry_attempt')
                    ->label('Retry #'),
                TextColumn::make('transaction_id')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('failure_message')
                    ->wrap()
                    ->placeholder('—'),
            ])
            ->defaultSort('attempted_at', 'desc')
            ->paginated([10, 25, 50])
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
        return (string) $ownerRecord->billingLedgerEntries()->count();
    }
}
