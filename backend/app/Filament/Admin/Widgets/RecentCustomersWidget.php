<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\Customers\CustomerResource;
use App\Models\Customer;
use App\Support\Admin\AdminAuthorization;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentCustomersWidget extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Recent customers';

    public static function canView(): bool
    {
        return AdminAuthorization::can(auth()->user(), 'customers.view');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Customer::query()->latest()->limit(6))
            ->columns([
                Tables\Columns\TextColumn::make('full_name')
                    ->label('Customer')
                    ->url(fn (Customer $record): string => CustomerResource::getUrl('view', ['record' => $record])),
                Tables\Columns\TextColumn::make('email'),
                Tables\Columns\TextColumn::make('orders_count')->label('Orders'),
                Tables\Columns\TextColumn::make('total_spent')->money(fn (Customer $record) => $record->currency ?? 'USD'),
                Tables\Columns\TextColumn::make('created_at')->since(),
            ])
            ->paginated(false);
    }
}
