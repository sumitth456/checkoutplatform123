<?php

namespace Tests\Feature\Order;

use App\Models\Order;
use App\Modules\Order\Enums\OrderPaymentStatus;
use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Order\Events\OrderCancelled;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderCancellationFeatureTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_rejects_cancelling_paid_order(): void
    {
        $order = Order::factory()->create([
            'status' => OrderStatus::Completed,
            'payment_status' => OrderPaymentStatus::Paid,
            'placed_at' => now(),
        ]);

        $response = $this->postJson("/api/v1/orders/{$order->uuid}/cancel");

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['order']);
    }

    #[Test]
    public function it_dispatches_event_when_pending_order_is_cancelled(): void
    {
        Event::fake([OrderCancelled::class]);

        $order = Order::factory()->create([
            'status' => OrderStatus::Pending,
            'payment_status' => OrderPaymentStatus::Pending,
            'placed_at' => now(),
        ]);

        $this->postJson("/api/v1/orders/{$order->uuid}/cancel", [
            'reason' => 'duplicate_order',
        ])->assertOk();

        Event::assertDispatched(OrderCancelled::class);
    }

    #[Test]
    public function it_returns_order_details_via_api(): void
    {
        $order = Order::factory()->create([
            'placed_at' => now(),
        ]);

        $this->getJson("/api/v1/orders/{$order->uuid}")
            ->assertOk()
            ->assertJsonPath('data.uuid', $order->uuid)
            ->assertJsonPath('data.order_number', $order->order_number);
    }
}
