<?php

namespace Tests\Feature\Order;

use App\Models\Order;
use App\Modules\Order\Enums\FulfillmentRecordStatus;
use App\Modules\Order\Services\DelayFulfillmentEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DelayFulfillmentEngineTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_scheduled_fulfillment_from_order_metadata(): void
    {
        $order = Order::factory()->create([
            'metadata' => ['fulfillment_delay_days' => 10],
        ]);

        $fulfillment = app(DelayFulfillmentEngineService::class)->createDelayedFulfillmentRecord($order);

        $this->assertSame(10, (int) $fulfillment->delay_days);
        $this->assertSame(FulfillmentRecordStatus::Scheduled, $fulfillment->status);
    }

    #[Test]
    public function it_submits_due_fulfillment_to_warehouse(): void
    {
        Http::fake([
            '*' => Http::response([
                'reference' => 'WH-1',
                'tracking_number' => 'TRK-1',
                'carrier' => 'UPS',
                'shipment_status' => 'shipped',
                'delivery_status' => 'delivered',
            ], 200),
        ]);

        $order = Order::factory()->create([
            'metadata' => ['fulfillment_delay_days' => 0],
        ]);

        $engine = app(DelayFulfillmentEngineService::class);
        $fulfillment = $engine->createDelayedFulfillmentRecord($order);
        $fulfillment->update(['scheduled_ship_date' => now()->startOfDay()]);

        $result = $engine->submitToWarehouse($fulfillment->fresh());

        $this->assertSame('TRK-1', $result->tracking_number);
    }
}
