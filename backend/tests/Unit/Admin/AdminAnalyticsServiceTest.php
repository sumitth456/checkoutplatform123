<?php

namespace Tests\Unit\Admin;

use App\Models\Order;
use App\Modules\Admin\Services\AdminAnalyticsService;
use App\Modules\Analytics\Data\AnalyticsPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_snapshot_returns_expected_keys(): void
    {
        $period = new AnalyticsPeriod(now()->startOfMonth(), now()->endOfDay(), 'USD');

        $snapshot = app(AdminAnalyticsService::class)->snapshot($period);

        $this->assertArrayHasKey('revenue_total', $snapshot);
        $this->assertArrayHasKey('revenue_trend', $snapshot);
        $this->assertArrayHasKey('top_products', $snapshot);
        $this->assertSame('USD', $snapshot['currency']);
    }

    public function test_snapshot_orders_total_respects_selected_period(): void
    {
        Order::factory()->create(['placed_at' => now()->subDays(10)]);
        Order::factory()->create(['placed_at' => now()->subDays(10)]);
        Order::factory()->create(['placed_at' => now()->subDays(60)]);

        $period = new AnalyticsPeriod(now()->subDays(14)->startOfDay(), now()->endOfDay(), 'USD');

        $snapshot = app(AdminAnalyticsService::class)->snapshot($period);

        $this->assertSame(2, $snapshot['orders_total']);
    }
}
