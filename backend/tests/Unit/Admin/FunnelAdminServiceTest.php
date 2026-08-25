<?php

namespace Tests\Unit\Admin;

use App\Models\Funnel;
use App\Models\Order;
use App\Modules\Admin\Services\FunnelAdminService;
use App\Modules\Order\Enums\FunnelStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FunnelAdminServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_tab_counts_by_status(): void
    {
        Funnel::factory()->create(['status' => FunnelStatus::Active]);
        Funnel::factory()->create(['status' => FunnelStatus::Draft]);
        Funnel::factory()->create(['status' => FunnelStatus::Archived]);

        $counts = app(FunnelAdminService::class)->tabCounts();

        $this->assertSame(3, $counts['all']);
        $this->assertSame(1, $counts['active']);
        $this->assertSame(1, $counts['draft']);
        $this->assertSame(1, $counts['archived']);
    }

    public function test_performance_sums_orders_for_funnel(): void
    {
        $funnel = Funnel::factory()->create(['currency' => 'USD']);

        Order::factory()->create([
            'funnel_id' => $funnel->id,
            'grand_total' => 50,
            'currency' => 'USD',
        ]);

        Order::factory()->create([
            'funnel_id' => $funnel->id,
            'grand_total' => 25,
            'currency' => 'USD',
        ]);

        $performance = app(FunnelAdminService::class)->performance($funnel);

        $this->assertSame(2, $performance['orders']);
        $this->assertSame(75.0, $performance['revenue']);
    }
}
