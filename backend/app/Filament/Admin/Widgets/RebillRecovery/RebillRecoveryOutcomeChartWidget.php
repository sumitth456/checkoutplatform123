<?php

namespace App\Filament\Admin\Widgets\RebillRecovery;

use App\Filament\Admin\Widgets\Analytics\Concerns\HasAnalyticsChartStyle;
use App\Filament\Admin\Widgets\RebillRecovery\Concerns\InteractsWithRebillRecoveryFilters;
use App\Support\Admin\AdminAuthorization;
use Filament\Widgets\ChartWidget;

class RebillRecoveryOutcomeChartWidget extends ChartWidget
{
    use HasAnalyticsChartStyle;
    use InteractsWithRebillRecoveryFilters;

    protected ?string $heading = 'Rebill outcome mix';

    protected ?string $description = 'Collected vs unrecovered vs retry still open';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '280px';

    public static function canView(): bool
    {
        return AdminAuthorization::can(auth()->user(), 'analytics.view');
    }

    protected function getType(): string
    {
        return 'doughnut';
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
        $outcome = $this->recoverySnapshot()['charts']['outcome'] ?? [];

        return [
            'labels' => array_column($outcome, 'label'),
            'datasets' => [
                [
                    'data' => array_column($outcome, 'value'),
                    'backgroundColor' => ['#22c55e', '#ef4444', '#f59e0b'],
                    'borderWidth' => 0,
                ],
            ],
        ];
    }
}
