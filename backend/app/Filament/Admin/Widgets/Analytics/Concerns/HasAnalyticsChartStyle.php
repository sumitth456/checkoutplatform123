<?php

namespace App\Filament\Admin\Widgets\Analytics\Concerns;

trait HasAnalyticsChartStyle
{
    /**
     * @return array<string, mixed>
     */
    protected function analyticsChartOptions(bool $hideLegend = true): array
    {
        return [
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => [
                    'display' => ! $hideLegend,
                    'labels' => [
                        'boxWidth' => 12,
                        'padding' => 16,
                        'font' => ['size' => 12],
                    ],
                ],
                'tooltip' => [
                    'backgroundColor' => 'rgba(15, 23, 42, 0.92)',
                    'padding' => 12,
                    'cornerRadius' => 8,
                    'titleFont' => ['size' => 12, 'weight' => '600'],
                    'bodyFont' => ['size' => 12],
                ],
            ],
            'scales' => [
                'x' => [
                    'grid' => ['display' => false],
                    'border' => ['display' => false],
                    'ticks' => [
                        'maxRotation' => 0,
                        'font' => ['size' => 11],
                        'color' => '#94a3b8',
                    ],
                ],
                'y' => [
                    'grid' => ['color' => 'rgba(148, 163, 184, 0.12)'],
                    'border' => ['display' => false],
                    'ticks' => [
                        'font' => ['size' => 11],
                        'color' => '#94a3b8',
                        'padding' => 8,
                    ],
                ],
            ],
            'elements' => [
                'line' => [
                    'tension' => 0.42,
                    'borderWidth' => 2,
                ],
                'point' => [
                    'radius' => 0,
                    'hoverRadius' => 5,
                    'hitRadius' => 12,
                ],
                'bar' => [
                    'borderRadius' => 6,
                    'borderSkipped' => false,
                ],
            ],
        ];
    }
}
