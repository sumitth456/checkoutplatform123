<?php

namespace Tests\Unit\Admin;

use App\Models\Fulfillment;
use App\Models\Order;
use App\Modules\Admin\Services\AdminDelayFulfillmentService;
use App\Modules\Order\Enums\FulfillmentRecordStatus;
use App\Modules\Order\Enums\OrderFulfillmentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDelayFulfillmentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_tab_counts_separate_queue_states(): void
    {
        $order = Order::factory()->create();

        Fulfillment::query()->create([
            'order_id' => $order->id,
            'delay_days' => 5,
            'scheduled_ship_date' => now()->addDays(5)->toDateString(),
            'status' => FulfillmentRecordStatus::Scheduled,
        ]);

        Fulfillment::query()->create([
            'order_id' => Order::factory()->create()->id,
            'delay_days' => 0,
            'scheduled_ship_date' => now()->toDateString(),
            'status' => FulfillmentRecordStatus::Scheduled,
        ]);

        Fulfillment::query()->create([
            'order_id' => Order::factory()->create()->id,
            'delay_days' => 10,
            'scheduled_ship_date' => now()->subDay()->toDateString(),
            'status' => FulfillmentRecordStatus::Scheduled,
        ]);

        $counts = app(AdminDelayFulfillmentService::class)->tabCounts();

        $this->assertSame(1, $counts['scheduled']);
        $this->assertSame(1, $counts['due_today']);
        $this->assertSame(1, $counts['overdue']);
    }

    public function test_cancel_marks_fulfillment_and_resets_order_status(): void
    {
        $order = Order::factory()->create([
            'fulfillment_status' => OrderFulfillmentStatus::Scheduled,
        ]);

        $fulfillment = Fulfillment::query()->create([
            'order_id' => $order->id,
            'delay_days' => 10,
            'scheduled_ship_date' => now()->addDays(10)->toDateString(),
            'status' => FulfillmentRecordStatus::Scheduled,
        ]);

        $updated = app(AdminDelayFulfillmentService::class)->cancel($fulfillment, 'Customer request');

        $this->assertSame(FulfillmentRecordStatus::Cancelled, $updated->status);
        $this->assertSame('Customer request', $updated->failure_reason);
        $this->assertSame(OrderFulfillmentStatus::Unfulfilled, $order->fresh()->fulfillment_status);
    }

    public function test_reschedule_updates_ship_date_and_delay_days(): void
    {
        $fulfillment = Fulfillment::query()->create([
            'order_id' => Order::factory()->create()->id,
            'delay_days' => 10,
            'scheduled_ship_date' => now()->addDays(10)->toDateString(),
            'status' => FulfillmentRecordStatus::Scheduled,
        ]);

        $newDate = now()->addDays(3)->startOfDay();
        $updated = app(AdminDelayFulfillmentService::class)->reschedule($fulfillment, $newDate);

        $this->assertTrue($updated->scheduled_ship_date->isSameDay($newDate));
        $this->assertSame(3, $updated->delay_days);
    }
}
