<?php

namespace App\Filament\Admin\Widgets\Analytics;

use App\Filament\Admin\Widgets\Analytics\Concerns\InteractsWithAnalyticsPeriod;
use App\Support\Admin\AdminAuthorization;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;

class AnalyticsTopFunnelsWidget extends TableWidget
{
    use InteractsWithAnalyticsPeriod;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '120s';

    public static function canView(): bool
    {
        return AdminAuthorization::can(auth()->user(), 'analytics.view');
    }

    public function table(Table $table): Table
    {
        $snapshot = $this->analyticsSnapshot();
        $currency = (string) $snapshot['currency'];
        $rows = collect($snapshot['top_funnels'] ?? [])
            ->values()
            ->map(fn (array $row, int $index): array => [
                'id' => (string) ($index + 1),
                'name' => $row['name'],
                'orders' => $row['orders'],
                'revenue' => $row['revenue'],
                'currency' => $currency,
            ]);

        return $table
            ->heading('Top funnels')
            ->description('By revenue in selected range')
            ->records(fn (): Collection => $rows)
            ->columns([
                TextColumn::make('name')->label('Funnel')->wrap(),
                TextColumn::make('orders')->numeric(),
                TextColumn::make('revenue')
                    ->formatStateUsing(fn ($state, array $record) => Number::currency((float) $state, $record['currency'])),
            ])
            ->paginated(false);
    }
}
