<?php

namespace Tests\Unit\Admin;

use App\Modules\Admin\Services\DashboardMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardMetricsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_metrics_returns_dashboard_data_structure(): void
    {
        $metrics = app(DashboardMetricsService::class)->metrics();

        $this->assertSame(0, $metrics->todayOrders);
        $this->assertSame(0.0, $metrics->todayRevenue);
        $this->assertSame('USD', $metrics->currency);
        $this->assertCount(30, $metrics->revenueTrend);
        $this->assertSame(now()->format('M j'), $metrics->revenueTrend[29]['label']);
    }
}
