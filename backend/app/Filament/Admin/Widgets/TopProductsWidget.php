<?php

namespace App\Filament\Admin\Widgets;

use App\Modules\Admin\Services\DashboardMetricsService;
use App\Support\Admin\AdminAuthorization;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;

class TopProductsWidget extends TableWidget
{
    protected static ?int $sort = 5;

    protected int | string | array $columnSpan = 1;

    protected ?string $pollingInterval = '120s';

    public static function canView(): bool
    {
        return AdminAuthorization::can(auth()->user(), 'dashboard.view');
    }

    public function table(Table $table): Table
    {
        $currency = app(DashboardMetricsService::class)->metrics()->currency;
        $rows = collect(app(DashboardMetricsService::class)->metrics()->topProducts)
            ->values()
            ->map(fn (array $row, int $index): array => [
                'id' => (string) ($index + 1),
                'name' => $row['name'],
                'orders' => $row['orders'],
                'revenue' => $row['revenue'],
                'currency' => $currency,
            ]);

        return $table
            ->heading('Top products')
            ->description('By revenue (month to date)')
            ->records(fn (): Collection => $rows)
            ->columns([
                TextColumn::make('name')
                    ->label('Product')
                    ->wrap(),
                TextColumn::make('orders')
                    ->label('Orders')
                    ->numeric(),
                TextColumn::make('revenue')
                    ->label('Revenue')
                    ->formatStateUsing(fn ($state, array $record) => Number::currency((float) $state, $record['currency'])),
            ])
            ->paginated(false);
    }
}
