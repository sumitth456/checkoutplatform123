<?php

namespace App\Filament\Admin\Widgets\Affiliate;

use App\Models\AffiliateClick;
use App\Models\AffiliateConversion;
use App\Support\Admin\AdminAuthorization;
use Filament\Widgets\ChartWidget;

class AffiliateClicksOrdersChartWidget extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Clicks & conversions';

    protected ?string $description = 'Daily clicks and conversions (30d)';

    protected int|string|array $columnSpan = 1;

    protected ?string $pollingInterval = '120s';

    public static function canView(): bool
    {
        return AdminAuthorization::can(auth()->user(), 'affiliate_dashboard.view');
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $start = now()->subDays(29)->startOfDay();
        $labels = [];
        $clicks = [];
        $conversions = [];

        for ($i = 0; $i < 30; $i++) {
            $date = $start->copy()->addDays($i);
            $labels[] = $date->format('M j');

            $clicks[] = AffiliateClick::query()
                ->whereDate('clicked_at', $date)
                ->count();

            $conversions[] = AffiliateConversion::query()
                ->whereDate('converted_at', $date)
                ->count();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Clicks',
                    'data' => $clicks,
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                ],
                [
                    'label' => 'Conversions',
                    'data' => $conversions,
                    'borderColor' => '#22c55e',
                    'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                    'fill' => true,
                ],
            ],
            'labels' => $labels,
        ];
    }
}
