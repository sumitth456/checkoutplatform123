<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Support\Admin\AdminAuthorization;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentOrdersWidget extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Recent orders';

    public static function canView(): bool
    {
        return AdminAuthorization::can(auth()->user(), 'orders.view');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Order::query()->latest('placed_at')->limit(8))
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->url(fn (Order $record): string => OrderResource::getUrl('view', ['record' => $record])),
                Tables\Columns\TextColumn::make('customer_email')->label('Customer'),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('payment_status')->badge(),
                Tables\Columns\TextColumn::make('grand_total')->money(fn (Order $record) => $record->currency),
                Tables\Columns\TextColumn::make('placed_at')->since(),
            ])
            ->paginated(false);
    }
}
