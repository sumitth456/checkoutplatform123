<?php

namespace Tests\Unit\Admin;

use App\Models\Order;
use App\Modules\Admin\Services\DashboardFilterApplicator;
use App\Modules\Order\Enums\OrderType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class DashboardFilterApplicatorOrdersTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_time_does_not_restrict_orders_by_date(): void
    {
        Order::factory()->create([
            'placed_at' => now()->subMonths(6),
            'order_type' => OrderType::Initial,
        ]);

        Order::factory()->create([
            'placed_at' => now(),
            'order_type' => OrderType::Initial,
        ]);

        $count = app(DashboardFilterApplicator::class)
            ->applyToOrders(Order::query(), Request::create('/', 'GET', ['df_range' => 'all_time']))
            ->count();

        $this->assertSame(2, $count);
    }

    public function test_today_filter_limits_orders_by_placed_at(): void
    {
        Order::factory()->create([
            'placed_at' => now()->subDay(),
            'order_type' => OrderType::Initial,
        ]);

        Order::factory()->create([
            'placed_at' => now(),
            'order_type' => OrderType::Initial,
        ]);

        $service = app(\App\Modules\Admin\Services\GlobalDashboardFilterService::class);
        [$from, $to] = $service->resolveQuickRange('today');

        $count = app(DashboardFilterApplicator::class)
            ->applyToOrders(Order::query(), Request::create('/', 'GET', [
                'df_range' => 'today',
                'df_from' => $from->toIso8601String(),
                'df_to' => $to->toIso8601String(),
            ]))
            ->count();

        $this->assertSame(1, $count);
    }

    public function test_date_filter_works_with_rebill_tab_param(): void
    {
        Order::factory()->create([
            'placed_at' => now(),
            'order_type' => OrderType::Rebill,
        ]);

        Order::factory()->create([
            'placed_at' => now(),
            'order_type' => OrderType::Initial,
        ]);

        $service = app(\App\Modules\Admin\Services\GlobalDashboardFilterService::class);
        [$from, $to] = $service->resolveQuickRange('today');

        $count = app(DashboardFilterApplicator::class)
            ->applyToOrders(Order::query(), Request::create('/', 'GET', [
                'df_range' => 'today',
                'df_from' => $from->toIso8601String(),
                'df_to' => $to->toIso8601String(),
                'tab' => 'rebill',
            ]))
            ->count();

        $this->assertSame(1, $count);
    }
}
