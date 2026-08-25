<?php

namespace App\Filament\Admin\Resources\Subscriptions\Schemas;

use App\Filament\Admin\Support\Infolists\AdminInfolistComponents;
use App\Filament\Admin\Resources\Customers\CustomerResource;
use App\Models\Subscription;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Schema;

class SubscriptionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                AdminInfolistComponents::summaryHeader(),
                SchemaView::make('filament.admin.components.compact-fields')
                    ->columnSpanFull()
                    ->viewData(fn (Subscription $record): array => [
                        'title' => 'Subscription summary',
                        'subtitle' => 'Billing cycle, product, and gateway',
                        'fields' => [
                            'Customer' => $record->customer?->email ?? '—',
                            'Plan' => $record->plan?->name ?? '—',
                            'Product' => $record->product?->name ?? '—',
                            'Variant' => $record->variant?->sku ?? '—',
                            'Current cycle' => (string) ($record->current_cycle_step ?? 1),
                            'Next charge' => $record->next_billing_at?->format('M j, Y H:i') ?? '—',
                            'Dunning stage' => $record->dunning_stage ?? '—',
                        ],
                        'urls' => [
                            'Customer' => $record->customer
                                ? CustomerResource::getUrl('view', ['record' => $record->customer])
                                : null,
                        ],
                    ]),
                AdminInfolistComponents::activityTimeline('Subscription journey'),
                AdminInfolistComponents::detailsSection('Billing details', [
                    Grid::make(3)->schema([
                        TextEntry::make('uuid')->copyable(),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('amount')->money(fn (Subscription $record) => $record->currency),
                        TextEntry::make('retry_count'),
                        TextEntry::make('dunning_stage')->placeholder('—'),
                        TextEntry::make('next_dunning_at')->dateTime()->placeholder('—'),
                        TextEntry::make('current_period_starts_at')->dateTime()->placeholder('—'),
                        TextEntry::make('current_period_ends_at')->dateTime()->placeholder('—'),
                        TextEntry::make('trial_ends_at')->dateTime()->placeholder('—'),
                        TextEntry::make('payment_provider')->placeholder('—'),
                        TextEntry::make('masked_payment_method')
                            ->label('Payment method')
                            ->state(fn (Subscription $record): string => $record->maskedPaymentMethod()),
                        TextEntry::make('paymentGateway.name')->label('Gateway')->placeholder('—'),
                        TextEntry::make('external_id')->label('External ID')->placeholder('—'),
                    ]),
                ]),
            ]);
    }
}
