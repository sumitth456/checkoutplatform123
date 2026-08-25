<?php

namespace App\Filament\Admin\Resources\Transactions\Schemas;

use App\Filament\Admin\Support\Infolists\AdminInfolistComponents;
use App\Filament\Admin\Resources\Customers\CustomerResource;
use App\Filament\Admin\Resources\Orders\OrderResource;
use App\Filament\Admin\Resources\Subscriptions\SubscriptionResource;
use App\Filament\Admin\Resources\Transactions\TransactionResource;
use App\Models\Transaction;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Schema;

class TransactionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                AdminInfolistComponents::summaryHeader(),
                SchemaView::make('filament.admin.components.compact-fields')
                    ->columnSpanFull()
                    ->viewData(fn (Transaction $record): array => [
                        'title' => 'Transaction summary',
                        'fields' => [
                            'Type' => str($record->type)->headline(),
                            'Status' => str($record->status)->headline(),
                            'Amount' => number_format((float) $record->amount, 2).' '.strtoupper((string) $record->currency),
                            'Customer' => $record->customer?->email ?? '—',
                            'Order' => $record->order?->order_number ?? '—',
                            'Gateway' => $record->paymentGateway?->name ?? '—',
                        ],
                        'urls' => [
                            'Customer' => $record->customer ? CustomerResource::getUrl('view', ['record' => $record->customer]) : null,
                            'Order' => $record->order ? OrderResource::getUrl('view', ['record' => $record->order]) : null,
                        ],
                    ]),
                AdminInfolistComponents::activityTimeline('Payment timeline'),
                AdminInfolistComponents::detailsSection('Transaction details', [
                    Grid::make(3)->schema([
                        TextEntry::make('uuid')->copyable(),
                        TextEntry::make('fee_amount')->money(fn (Transaction $record) => $record->currency)->placeholder('—'),
                        TextEntry::make('net_amount')->money(fn (Transaction $record) => $record->currency)->placeholder('—'),
                        TextEntry::make('processed_at')->dateTime()->placeholder('—'),
                        TextEntry::make('subscription.uuid')
                            ->label('Subscription')
                            ->url(fn (Transaction $record): ?string => $record->subscription
                                ? SubscriptionResource::getUrl('view', ['record' => $record->subscription])
                                : null)
                            ->placeholder('—'),
                        TextEntry::make('parentTransaction.uuid')
                            ->label('Parent transaction')
                            ->url(fn (Transaction $record): ?string => $record->parentTransaction
                                ? TransactionResource::getUrl('view', ['record' => $record->parentTransaction])
                                : null)
                            ->placeholder('—'),
                        TextEntry::make('masked_payment_method')
                            ->label('Method')
                            ->state(fn (Transaction $record): string => $record->maskedPaymentMethod()),
                        TextEntry::make('external_id')->label('Gateway transaction ID')->copyable()->placeholder('—'),
                        TextEntry::make('failure_code')->placeholder('—'),
                        TextEntry::make('failure_message')->columnSpanFull()->placeholder('—'),
                        TextEntry::make('gateway_response')
                            ->formatStateUsing(fn (?array $state): string => $state !== null
                                ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                                : '—')
                            ->columnSpanFull()
                            ->prose(),
                    ]),
                ]),
            ]);
    }
}
