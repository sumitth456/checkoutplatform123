<?php

namespace App\Filament\Admin\Widgets\Fulfillment;

use App\Models\Fulfillment;
use App\Support\Admin\AdminAuthorization;
use Filament\Widgets\ChartWidget;

class FulfillmentVolumeChartWidget extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Daily ship volume';

    protected ?string $description = 'Fulfillments shipped over the last 30 days';

    protected int|string|array $columnSpan = 1;

    protected ?string $pollingInterval = '120s';

    public static function canView(): bool
    {
        return AdminAuthorization::can(auth()->user(), 'fulfillment.view')
            || AdminAuthorization::can(auth()->user(), 'delay_fulfillment.view');
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $start = now()->subDays(29)->startOfDay();
        $labels = [];
        $data = [];

        for ($i = 0; $i < 30; $i++) {
            $date = $start->copy()->addDays($i);
            $labels[] = $date->format('M j');

            $data[] = Fulfillment::query()
                ->whereDate('shipped_at', $date)
                ->count();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Shipped',
                    'data' => $data,
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                ],
            ],
            'labels' => $labels,
        ];
    }
}
