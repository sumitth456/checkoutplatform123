<?php

namespace App\Filament\Admin\Widgets\RebillRecovery;

use App\Filament\Admin\Widgets\Analytics\Concerns\HasAnalyticsChartStyle;
use App\Filament\Admin\Widgets\RebillRecovery\Concerns\InteractsWithRebillRecoveryFilters;
use App\Support\Admin\AdminAuthorization;
use Filament\Widgets\ChartWidget;

class RebillRecoveryTrendChartWidget extends ChartWidget
{
    use HasAnalyticsChartStyle;
    use InteractsWithRebillRecoveryFilters;

    protected ?string $heading = 'Recovery trend';

    protected ?string $description = 'Cycles collected per day and retry-recovered revenue';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '300px';

    public static function canView(): bool
    {
        return AdminAuthorization::can(auth()->user(), 'analytics.view');
    }

    protected function getType(): string
    {
        return 'line';
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
        $trend = $this->recoverySnapshot()['charts']['trend'] ?? [];

        return [
            'labels' => array_column($trend, 'label'),
            'datasets' => [
                [
                    'label' => 'Cycles collected',
                    'data' => array_column($trend, 'collected'),
                    'borderColor' => '#6366f1',
                    'backgroundColor' => 'rgba(99, 102, 241, 0.08)',
                    'yAxisID' => 'y',
                    'fill' => true,
                    'tension' => 0.35,
                ],
                [
                    'label' => 'Retry revenue',
                    'data' => array_column($trend, 'recovered_revenue'),
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.08)',
                    'yAxisID' => 'y1',
                    'fill' => false,
                    'tension' => 0.35,
                ],
            ],
        ];
    }
}
