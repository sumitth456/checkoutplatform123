<?php

namespace App\Filament\Admin\Resources\Transactions\RelationManagers;

use App\Filament\Admin\Resources\Transactions\TransactionResource;
use App\Models\Transaction;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class RelatedTransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'childTransactions';

    protected static ?string $title = 'Related transactions';

    public function form(Schema $schema): Schema
    {
        return $schema;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('uuid')
                    ->limit(12)
                    ->copyable(),
                TextColumn::make('type')
                    ->badge(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('amount')
                    ->money(fn (Transaction $record) => $record->currency),
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
        return (string) $ownerRecord->childTransactions()->count();
    }
}
