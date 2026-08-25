<?php

namespace App\Filament\Admin\Resources\Customers\RelationManagers;

use App\Models\CustomerPaymentMethod;
use Filament\Actions\DeleteAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaymentMethodsRelationManager extends RelationManager
{
    protected static string $relationship = 'paymentMethods';

    protected static ?string $title = 'Saved payment methods';

    protected static bool $shouldSkipAuthorization = true;

    public function form(Schema $schema): Schema
    {
        return $schema;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('payment_method')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => strtoupper($state)),
                TextColumn::make('displayLabel')
                    ->label('Account')
                    ->state(fn (CustomerPaymentMethod $record): string => $record->displayLabel()),
                TextColumn::make('provider')->placeholder('—'),
                TextColumn::make('bank_name')->placeholder('—'),
                IconColumn::make('is_default')->boolean()->label('Default'),
                TextColumn::make('created_at')->dateTime(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                DeleteAction::make()
                    ->label('Remove')
                    ->modalHeading('Remove saved payment method')
                    ->modalDescription('This will remove the saved bank account. Future rebills will fail unless a new payment method is added.'),
            ])
            ->headerActions([])
            ->toolbarActions([]);
    }

    public function isReadOnly(): bool
    {
        return false;
    }
}
