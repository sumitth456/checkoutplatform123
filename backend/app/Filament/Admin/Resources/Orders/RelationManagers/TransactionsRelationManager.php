<?php

namespace App\Filament\Admin\Resources\Orders\RelationManagers;

use App\Filament\Admin\Resources\Transactions\TransactionResource;
use App\Models\Transaction;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';

    protected static ?string $title = 'Transactions';

    protected static bool $shouldSkipAuthorization = true;

    public function form(Schema $schema): Schema
    {
        return $schema;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('uuid')
                    ->limit(8)
                    ->copyable(),
                TextColumn::make('type')
                    ->badge(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('amount')
                    ->money(fn (Transaction $record) => $record->currency),
                TextColumn::make('paymentGateway.name')
                    ->label('Gateway')
                    ->placeholder('—'),
                TextColumn::make('external_id')
                    ->label('Gateway ID')
                    ->toggleable()
                    ->placeholder('—'),
                TextColumn::make('failure_message')
                    ->wrap()
                    ->toggleable()
                    ->placeholder('—'),
                TextColumn::make('processed_at')
                    ->dateTime(),
            ])
            ->defaultSort('processed_at', 'desc')
            ->recordActions([
                ViewAction::make()
                    ->url(fn (Transaction $record): string => TransactionResource::getUrl('view', ['record' => $record])),
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
        return (string) $ownerRecord->transactions()->count();
    }
}
