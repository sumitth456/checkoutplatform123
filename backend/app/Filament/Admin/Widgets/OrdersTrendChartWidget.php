<?php

namespace App\Filament\Admin\Widgets;

use App\Modules\Admin\Services\DashboardMetricsService;
use App\Support\Admin\AdminAuthorization;
use Filament\Widgets\ChartWidget;

class OrdersTrendChartWidget extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Orders trend';

    protected ?string $description = 'Paid orders per day (30d)';

    protected int | string | array $columnSpan = 1;

    protected ?string $pollingInterval = '120s';

    public static function canView(): bool
    {
        return AdminAuthorization::can(auth()->user(), 'dashboard.view');
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $trend = app(DashboardMetricsService::class)->metrics()->revenueTrend;

        return [
            'datasets' => [
                [
                    'label' => 'Orders',
                    'data' => array_column($trend, 'orders'),
                    'backgroundColor' => '#3b82f6',
                ],
            ],
            'labels' => array_column($trend, 'label'),
        ];
    }
}
