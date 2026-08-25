<?php

namespace App\Filament\Admin\Widgets\Analytics;

use App\Filament\Admin\Widgets\Analytics\Concerns\HasAnalyticsChartStyle;
use App\Filament\Admin\Widgets\Analytics\Concerns\InteractsWithAnalyticsPeriod;
use App\Support\Admin\AdminAuthorization;
use Filament\Widgets\ChartWidget;

class AnalyticsOrdersTrendWidget extends ChartWidget
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
        return $this->analyticsChartOptions();
    }

    protected function getData(): array
    {
        $trend = $this->analyticsSnapshot()['revenue_trend'] ?? [];

        return [
            'datasets' => [
                [
                    'label' => 'Orders',
                    'data' => array_column($trend, 'orders'),
                    'backgroundColor' => 'rgba(99, 102, 241, 0.75)',
                    'hoverBackgroundColor' => '#6366f1',
                    'borderRadius' => 6,
                ],
            ],
            'labels' => array_column($trend, 'label'),
        ];
    }
}
