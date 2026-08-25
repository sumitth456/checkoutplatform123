<?php

namespace App\Filament\Admin\Resources\Customers\Schemas;

use App\Filament\Admin\Support\Infolists\AdminInfolistComponents;
use App\Models\Customer;
use App\Support\Admin\AddressFormatter;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Schema;

class CustomerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                AdminInfolistComponents::summaryHeader(),
                SchemaView::make('filament.admin.components.compact-fields')
                    ->columnSpanFull()
                    ->viewData(fn (Customer $record): array => [
                        'title' => 'Contact & addresses',
                        'subtitle' => 'Customer profile summary',
                        'fields' => [
                            'Email' => $record->email,
                            'Phone' => $record->phone ?? '—',
                            'Company' => $record->company ?? '—',
                            'Shipping' => self::formatAddress($record->shipping_address),
                            'Billing' => self::formatAddress($record->billing_address),
                        ],
                        'urls' => [],
                    ]),
                AdminInfolistComponents::activityTimeline('Customer lifecycle'),
                AdminInfolistComponents::detailsSection('Profile details', [
                    Grid::make(3)->schema([
                        TextEntry::make('status')->badge(),
                        TextEntry::make('currency')->placeholder('—'),
                        TextEntry::make('orders_count')->numeric(),
                        TextEntry::make('total_spent')->money(fn (Customer $record) => $record->currency ?? 'USD'),
                        TextEntry::make('first_order_at')->dateTime()->placeholder('—'),
                        TextEntry::make('last_order_at')->dateTime()->placeholder('—'),
                        IconEntry::make('accepts_marketing')->boolean(),
                        IconEntry::make('is_tax_exempt')->label('Tax exempt')->boolean(),
                        TextEntry::make('uuid')->copyable()->columnSpanFull(),
                    ]),
                ]),
            ]);
    }

    public static function formatAddress(mixed $address): string
    {
        return AddressFormatter::format($address);
    }
}
