<?php

namespace App\Filament\Admin\Widgets\Analytics;

use App\Filament\Admin\Widgets\Analytics\Concerns\HasAnalyticsChartStyle;
use App\Filament\Admin\Widgets\Analytics\Concerns\InteractsWithAnalyticsPeriod;
use App\Support\Admin\AdminAuthorization;
use Filament\Widgets\ChartWidget;

class AnalyticsGatewayHealthWidget extends ChartWidget
{
    use HasAnalyticsChartStyle;
    use InteractsWithAnalyticsPeriod;

    protected ?string $heading = null;

    protected ?string $description = null;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '120s';

    protected ?string $maxHeight = '300px';

    public static function canView(): bool
    {
        return AdminAuthorization::can(auth()->user(), 'analytics.view');
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function getOptions(): ?array
    {
        return $this->analyticsChartOptions(hideLegend: false);
    }

    protected function getData(): array
    {
        $gateways = $this->analyticsSnapshot()['gateway_health'] ?? [];

        if ($gateways === []) {
            return [
                'datasets' => [
                    [
                        'label' => 'Success %',
                        'data' => [100],
                        'backgroundColor' => 'rgba(5, 150, 105, 0.75)',
                        'borderRadius' => 6,
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
                    'backgroundColor' => 'rgba(5, 150, 105, 0.75)',
                    'hoverBackgroundColor' => '#059669',
                    'borderRadius' => 6,
                ],
            ],
            'labels' => array_column($gateways, 'name'),
        ];
    }
}
