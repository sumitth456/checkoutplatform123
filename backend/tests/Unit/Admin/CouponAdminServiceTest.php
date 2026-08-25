<?php

namespace Tests\Unit\Admin;

use App\Models\Coupon;
use App\Modules\Admin\Services\CouponAdminService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponAdminServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_tab_counts_classify_coupon_states(): void
    {
        Coupon::factory()->create([
            'code' => 'ACTIVE1',
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addWeek(),
        ]);

        Coupon::factory()->create([
            'code' => 'FUTURE1',
            'is_active' => true,
            'starts_at' => now()->addWeek(),
        ]);

        Coupon::factory()->create([
            'code' => 'OLD1',
            'is_active' => true,
            'expires_at' => now()->subDay(),
        ]);

        Coupon::factory()->create([
            'code' => 'OFF1',
            'is_active' => false,
        ]);

        $counts = app(CouponAdminService::class)->tabCounts();

        $this->assertSame(4, $counts['all']);
        $this->assertSame(1, $counts['active']);
        $this->assertSame(1, $counts['scheduled']);
        $this->assertSame(1, $counts['expired']);
        $this->assertSame(1, $counts['inactive']);
    }
}
