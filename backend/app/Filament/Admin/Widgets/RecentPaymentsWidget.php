<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\Transactions\TransactionResource;
use App\Models\Transaction;
use App\Support\Admin\AdminAuthorization;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentPaymentsWidget extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    protected static ?string $heading = 'Recent payments';

    public static function canView(): bool
    {
        return AdminAuthorization::can(auth()->user(), 'transactions.view');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Transaction::query()
                    ->whereIn('type', ['charge', 'capture', 'sale', 'payment'])
                    ->latest('processed_at')
                    ->limit(6)
            )
            ->columns([
                Tables\Columns\TextColumn::make('uuid')
                    ->label('Transaction')
                    ->limit(8)
                    ->url(fn (Transaction $record): string => TransactionResource::getUrl('view', ['record' => $record])),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('amount')->money(fn (Transaction $record) => $record->currency),
                Tables\Columns\TextColumn::make('processed_at')->since(),
            ])
            ->paginated(false);
    }
}
