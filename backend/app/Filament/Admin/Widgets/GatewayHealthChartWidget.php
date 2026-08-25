<?php

namespace App\Filament\Admin\Widgets;

use App\Modules\Admin\Services\DashboardMetricsService;
use App\Support\Admin\AdminAuthorization;
use Filament\Widgets\ChartWidget;

class GatewayHealthChartWidget extends ChartWidget
{
    protected static ?int $sort = 4;

    protected ?string $heading = 'Gateway success rate';

    protected ?string $description = 'Current month by gateway';

    protected int | string | array $columnSpan = 'full';

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
        $gateways = app(DashboardMetricsService::class)->metrics()->gatewayHealth;

        if ($gateways === []) {
            return [
                'datasets' => [
                    [
                        'label' => 'Success %',
                        'data' => [100],
                        'backgroundColor' => '#22c55e',
                    ],
                ],
                'labels' => ['No gateway traffic yet'],
            ];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Success %',
                    'data' => array_column($gateways, 'success_rate'),
                    'backgroundColor' => '#22c55e',
                ],
                [
                    'label' => 'Failed txns',
                    'data' => array_column($gateways, 'failed'),
                    'backgroundColor' => '#ef4444',
                ],
            ],
            'labels' => array_column($gateways, 'name'),
        ];
    }
}
