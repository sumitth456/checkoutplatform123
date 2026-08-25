<?php

namespace App\Filament\Admin\Resources\Transactions\Tables;

use App\Filament\Admin\Exports\TransactionExporter;
use App\Models\Transaction;
use App\Support\Admin\AdminAuthorization;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['customer', 'order', 'paymentGateway']))
            ->columns([
                TextColumn::make('uuid')
                    ->label('ID')
                    ->searchable()
                    ->copyable()
                    ->limit(12),
                TextColumn::make('processed_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'failed' => 'danger',
                        'refunded' => 'warning',
                        'pending' => 'gray',
                        default => 'success',
                    })
                    ->sortable(),
                TextColumn::make('amount')
                    ->money(fn (Transaction $record) => $record->currency)
                    ->sortable(),
                TextColumn::make('customer.email')
                    ->label('Customer')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('order.order_number')
                    ->label('Order')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('paymentGateway.name')
                    ->label('Gateway')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('external_id')
                    ->label('Gateway ID')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—'),
                TextColumn::make('payment_method_brand')
                    ->label('Card')
                    ->formatStateUsing(fn ($state, Transaction $record): string => $record->maskedPaymentMethod())
                    ->toggleable(),
                TextColumn::make('failure_message')
                    ->wrap()
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—'),
            ])
            ->defaultSort('processed_at', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'capture' => 'Capture',
                        'authorization' => 'Authorization',
                        'refund' => 'Refund',
                        'void' => 'Void',
                    ]),
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'captured' => 'Captured',
                        'succeeded' => 'Succeeded',
                        'completed' => 'Completed',
                        'paid' => 'Paid',
                        'failed' => 'Failed',
                        'refunded' => 'Refunded',
                    ]),
                SelectFilter::make('payment_gateway_id')
                    ->label('Gateway')
                    ->relationship('paymentGateway', 'name'),
                Filter::make('processed_at')
                    ->schema([
                        DatePicker::make('from')
                            ->label('From'),
                        DatePicker::make('until')
                            ->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('processed_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('processed_at', '<=', $date));
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                ExportBulkAction::make()
                    ->exporter(TransactionExporter::class)
                    ->authorize(fn (): bool => AdminAuthorization::can(auth()->user(), 'transactions.export')),
            ]);
    }
}
