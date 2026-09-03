<?php

namespace Tests\Unit\Order;

use App\Models\FulfillmentHouse;
use App\Models\Order;
use App\Models\Transaction;
use App\Modules\Order\Services\OrderTestDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTestDetectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_detects_shipstation_admin_test_metadata_flag(): void
    {
        $order = Order::factory()->create([
            'metadata' => ['shipstation_test_order' => true],
        ]);

        $this->assertTrue(app(OrderTestDetector::class)->isTestOrder($order));
    }

    public function test_detects_ss_test_order_number_prefix(): void
    {
        $order = Order::factory()->create([
            'order_number' => 'SS-TEST-20260903120000',
        ]);

        $this->assertTrue(app(OrderTestDetector::class)->isTestOrder($order));
    }

    public function test_detects_sandbox_capture_transaction(): void
    {
        $order = Order::factory()->create();

        Transaction::query()->create([
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'type' => 'capture',
            'status' => 'captured',
            'external_id' => 'pi_sandbox_'.fake()->uuid(),
            'currency' => 'USD',
            'amount' => 10,
            'processed_at' => now(),
            'gateway_response' => ['sandbox' => true],
        ]);

        $this->assertTrue(app(OrderTestDetector::class)->isTestOrder($order->fresh(['transactions'])));
    }

    public function test_real_order_is_not_detected_as_test(): void
    {
        $order = Order::factory()->create([
            'order_number' => 'ORD-20260903-ABC123',
        ]);

        Transaction::query()->create([
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'type' => 'capture',
            'status' => 'captured',
            'external_id' => 'pi_live_123456',
            'currency' => 'USD',
            'amount' => 10,
            'processed_at' => now(),
            'gateway_response' => ['payment_intent_id' => 'pi_live_123456'],
        ]);

        $this->assertFalse(app(OrderTestDetector::class)->isTestOrder($order->fresh(['transactions'])));
    }
}
