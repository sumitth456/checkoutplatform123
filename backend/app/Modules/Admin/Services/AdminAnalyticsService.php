<?php

namespace App\Modules\Admin\Services;

use App\Modules\Analytics\Data\AnalyticsPeriod;

class AdminAnalyticsService
{
    public function __construct(
        private readonly DashboardMetricsService $dashboardMetrics,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function snapshot(AnalyticsPeriod $period): array
    {
        return $this->dashboardMetrics->periodSnapshot($period);
    }
}
