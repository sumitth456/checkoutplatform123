<?php

namespace App\Filament\Admin\Widgets\LandingPages;

use App\Filament\Admin\Widgets\LandingPages\Concerns\InteractsWithLandingPageAnalyticsPeriod;
use App\Support\Admin\AdminAuthorization;
use Filament\Widgets\ChartWidget;

class LandingPageViewsTrendWidget extends ChartWidget
{
    use InteractsWithLandingPageAnalyticsPeriod;

    protected ?string $heading = 'Views & conversions';

    protected ?string $description = 'Daily traffic and attributed paid orders';

    protected int|string|array $columnSpan = 1;

    protected ?string $pollingInterval = '120s';

    public static function canView(): bool
    {
        return AdminAuthorization::can(auth()->user(), 'landing_pages.view');
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $snapshot = $this->landingPageAnalyticsSnapshot();
        $viewsTrend = $snapshot['views_trend'] ?? [];
        $conversionsTrend = $snapshot['conversions_trend'] ?? [];

        return [
            'datasets' => [
                [
                    'label' => 'Views',
                    'data' => array_column($viewsTrend, 'views'),
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.12)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Conversions',
                    'data' => array_column($conversionsTrend, 'conversions'),
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.12)',
                    'fill' => false,
                    'tension' => 0.3,
                ],
            ],
            'labels' => array_column($viewsTrend, 'label'),
        ];
    }
}
