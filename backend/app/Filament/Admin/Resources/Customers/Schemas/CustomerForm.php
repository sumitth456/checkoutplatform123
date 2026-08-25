<?php

namespace App\Filament\Admin\Resources\Customers\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Profile')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('first_name')
                                ->maxLength(255),
                            TextInput::make('last_name')
                                ->maxLength(255),
                            TextInput::make('email')
                                ->email()
                                ->required()
                                ->maxLength(255)
                                ->unique(ignoreRecord: true),
                            TextInput::make('phone')
                                ->tel()
                                ->maxLength(50),
                            TextInput::make('company')
                                ->maxLength(255),
                            TextInput::make('tax_id')
                                ->label('Tax ID')
                                ->maxLength(255),
                            Select::make('status')
                                ->options([
                                    'active' => 'Active',
                                    'inactive' => 'Inactive',
                                    'blocked' => 'Blocked',
                                ])
                                ->required()
                                ->default('active'),
                            TextInput::make('locale')
                                ->default('en')
                                ->maxLength(10),
                            TextInput::make('currency')
                                ->maxLength(3)
                                ->placeholder('USD'),
                            Toggle::make('accepts_marketing'),
                            Toggle::make('is_tax_exempt')
                                ->label('Tax exempt'),
                        ]),
                    ]),
                Section::make('Billing address')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema(
                            CustomerAddressSchema::fields('billing_address', 'Billing'),
                        ),
                    ]),
                Section::make('Shipping address')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema(
                            CustomerAddressSchema::fields('shipping_address', 'Shipping'),
                        ),
                    ]),
            ]);
    }
}
