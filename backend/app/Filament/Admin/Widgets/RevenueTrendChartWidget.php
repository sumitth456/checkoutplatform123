<?php

namespace App\Filament\Admin\Widgets;

use App\Modules\Admin\Services\DashboardMetricsService;
use App\Support\Admin\AdminAuthorization;
use Filament\Widgets\ChartWidget;

class RevenueTrendChartWidget extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Revenue trend';

    protected ?string $description = 'Last 30 days net revenue';

    protected int | string | array $columnSpan = 1;

    protected ?string $pollingInterval = '120s';

    public static function canView(): bool
    {
        return AdminAuthorization::can(auth()->user(), 'dashboard.view');
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $trend = app(DashboardMetricsService::class)->metrics()->revenueTrend;

        return [
            'datasets' => [
                [
                    'label' => 'Revenue',
                    'data' => array_column($trend, 'revenue'),
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.15)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => array_column($trend, 'label'),
        ];
    }
}
