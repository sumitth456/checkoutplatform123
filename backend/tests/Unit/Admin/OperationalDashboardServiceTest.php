<?php

namespace Tests\Unit\Admin;

use App\Modules\Admin\Data\DashboardFilter;
use App\Modules\Admin\Services\GlobalDashboardFilterService;
use App\Modules\Admin\Services\OperationalDashboardService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalDashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_snapshot_returns_expected_structure(): void
    {
        $filter = app(GlobalDashboardFilterService::class)->resolve(
            quickRange: 'last_7_days',
        );

        $snapshot = app(OperationalDashboardService::class)->snapshot($filter);

        $this->assertArrayHasKey('kpis', $snapshot);
        $this->assertArrayHasKey('sales', $snapshot);
        $this->assertArrayHasKey('customer_service', $snapshot);
        $this->assertArrayHasKey('fulfillment', $snapshot);
        $this->assertArrayHasKey('subscriptions', $snapshot);
        $this->assertArrayHasKey('affiliates', $snapshot);
        $this->assertArrayHasKey('gateways', $snapshot);
        $this->assertArrayHasKey('charts', $snapshot);
        $this->assertArrayHasKey('tops', $snapshot);

        $this->assertArrayHasKey('orders', $snapshot['kpis']);
        $this->assertArrayHasKey('url', $snapshot['kpis']['orders']);
        $this->assertArrayHasKey('change', $snapshot['kpis']['orders']);
        $this->assertArrayHasKey('sparkline', $snapshot['kpis']['orders']);
    }

    public function test_dashboard_filter_query_params_round_trip(): void
    {
        $from = Carbon::parse('2026-08-01 00:00:00');
        $to = Carbon::parse('2026-08-01 23:59:59');

        $filter = new DashboardFilter(
            from: $from,
            to: $to,
            previousFrom: $from->copy()->subDay(),
            previousTo: $to->copy()->subDay(),
            quickRange: 'today',
            campaignId: 5,
        );

        $params = $filter->toQueryParams();

        $this->assertSame('today', $params['df_range']);
        $this->assertSame(5, $params['df_campaign']);
        $this->assertArrayHasKey('df_from', $params);
        $this->assertArrayHasKey('df_to', $params);
    }

    public function test_quick_range_presets_resolve_dates(): void
    {
        $service = app(GlobalDashboardFilterService::class);

        [$from, $to] = $service->resolveQuickRange('today');

        $this->assertTrue($from->isToday());
        $this->assertTrue($to->isToday());
    }
}
