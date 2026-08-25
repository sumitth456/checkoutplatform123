<?php

namespace Tests\Unit\Admin;

use App\Models\Customer;
use App\Models\Order;
use App\Modules\Admin\Services\OrderDuplicateDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderDuplicateDetectionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_detects_matching_orders_in_window(): void
    {
        $customer = Customer::factory()->create();
        $placedAt = now()->subHour();

        $original = Order::factory()->create([
            'customer_id' => $customer->id,
            'customer_email' => $customer->email,
            'grand_total' => 99.00,
            'ip_address' => '203.0.113.10',
            'placed_at' => $placedAt,
        ]);

        Order::factory()->create([
            'customer_id' => $customer->id,
            'customer_email' => $customer->email,
            'grand_total' => 99.00,
            'ip_address' => '203.0.113.10',
            'placed_at' => $placedAt->copy()->addMinutes(10),
        ]);

        $service = app(OrderDuplicateDetectionService::class);

        $this->assertSame(1, $service->countPotentialDuplicates($original));
    }
}
