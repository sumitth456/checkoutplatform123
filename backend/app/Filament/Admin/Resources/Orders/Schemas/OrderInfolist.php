<?php

namespace App\Filament\Admin\Resources\Orders\Schemas;

use App\Filament\Admin\Resources\Customers\CustomerResource;
use App\Filament\Admin\Support\Infolists\AdminInfolistComponents;
use App\Models\Order;
use App\Modules\Admin\Services\OrderRebillDetailsService;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Schema;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                SchemaView::make('filament.admin.components.compact-fields')
                    ->columnSpanFull()
                    ->viewData(function (Order $record): array {
                        $service = app(OrderRebillDetailsService::class);
                        $record->loadMissing('customer');

                        return [
                            'title' => 'Customer information',
                            'subtitle' => 'Shipping, billing, and contact summary',
                            'fields' => [
                                'Customer' => $record->customer?->full_name ?? $record->customer_email,
                                'Email' => $record->customer_email ?? '—',
                                'Phone' => $record->customer_phone ?? $record->customer?->phone ?? '—',
                                'Shipping' => $service->orderShippingAddress($record),
                                'Billing' => $service->orderBillingAddress($record),
                            ],
                            'urls' => [
                                'Customer' => $record->customer
                                    ? CustomerResource::getUrl('view', ['record' => $record->customer])
                                    : null,
                            ],
                        ];
                    }),
                AdminInfolistComponents::activityTimeline('Order lifecycle'),
                AdminInfolistComponents::detailsSection('Order details', [
                    Grid::make(3)->schema([
                        TextEntry::make('subtotal')->money(fn (Order $record) => $record->currency),
                        TextEntry::make('discount_total')->money(fn (Order $record) => $record->currency),
                        TextEntry::make('tax_total')->money(fn (Order $record) => $record->currency),
                        TextEntry::make('shipping_total')->money(fn (Order $record) => $record->currency),
                        TextEntry::make('grand_total')->money(fn (Order $record) => $record->currency),
                        TextEntry::make('refunded_total')->money(fn (Order $record) => $record->currency),
                        TextEntry::make('paymentGateway.name')->label('Gateway')->placeholder('—'),
                        TextEntry::make('payment_method_type')
                            ->label('Payment method')
                            ->formatStateUsing(fn (?string $state): string => strtoupper((string) ($state ?: 'card')))
                            ->badge(),
                        TextEntry::make('metadata.authorization_id')
                            ->label('Authorization ID')
                            ->placeholder('—')
                            ->visible(fn (Order $record): bool => $record->payment_method_type === 'ach'),
                        TextEntry::make('metadata.last4')
                            ->label('Last 4 digits')
                            ->formatStateUsing(fn (?string $state): string => filled($state) ? '••••'.$state : '—')
                            ->visible(fn (Order $record): bool => $record->payment_method_type === 'ach'),
                        TextEntry::make('metadata.account_type')
                            ->label('Account type')
                            ->formatStateUsing(fn (?string $state): string => filled($state) ? ucfirst($state) : '—')
                            ->visible(fn (Order $record): bool => $record->payment_method_type === 'ach'),
                        TextEntry::make('payment_status')->badge(),
                        TextEntry::make('fraud_status')->badge(),
                        TextEntry::make('fulfillment_status')->badge(),
                        TextEntry::make('ip_address')->placeholder('—'),
                        TextEntry::make('device_type')->placeholder('—'),
                        TextEntry::make('country_code')->placeholder('—'),
                    ]),
                ]),
            ]);
    }
}
