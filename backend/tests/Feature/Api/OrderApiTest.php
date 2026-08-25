<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Variant;
use App\Modules\Order\Enums\OrderPaymentStatus;
use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Order\Events\OrderPaid;
use App\Modules\Order\Events\OrderPlaced;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_places_order_with_line_items(): void
    {
        Event::fake([OrderPlaced::class]);

        $product = Product::factory()->create(['currency' => 'USD']);
        $variant = Variant::factory()->create([
            'product_id' => $product->id,
            'price' => 49.99,
            'currency' => 'USD',
            'track_inventory' => false,
        ]);

        $response = $this->postJson('/api/v1/orders', [
            'customer' => [
                'email' => 'buyer@example.com',
                'first_name' => 'Jane',
                'last_name' => 'Buyer',
            ],
            'items' => [
                ['variant_uuid' => $variant->uuid, 'quantity' => 2],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.payment_status', 'pending')
            ->assertJsonPath('data.grand_total', '99.98')
            ->assertJsonCount(1, 'data.items');

        $this->assertDatabaseHas('orders', [
            'customer_email' => 'buyer@example.com',
            'grand_total' => 99.98,
        ]);

        Event::assertDispatched(OrderPlaced::class);
    }

    #[Test]
    public function it_checkouts_order_and_marks_paid(): void
    {
        Http::fake([
            'api.stripe.com/*' => Http::sequence()
                ->push([
                    'id' => 'pi_order_checkout',
                    'client_secret' => 'secret',
                    'status' => 'requires_capture',
                ], 200)
                ->push([
                    'id' => 'pi_order_checkout',
                    'status' => 'succeeded',
                ], 200),
        ]);

        Event::fake([OrderPaid::class]);

        $customer = Customer::factory()->create();
        $variant = Variant::factory()->create([
            'price' => 25.00,
            'currency' => 'USD',
            'track_inventory' => false,
        ]);

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::Pending,
            'payment_status' => OrderPaymentStatus::Pending,
            'currency' => 'USD',
            'subtotal' => 25.00,
            'grand_total' => 25.00,
            'placed_at' => now(),
        ]);

        $order->items()->create([
            'product_id' => $variant->product_id,
            'variant_id' => $variant->id,
            'name' => 'Test Item',
            'sku' => $variant->sku,
            'quantity' => 1,
            'unit_price' => 25.00,
            'total' => 25.00,
        ]);

        $response = $this->postJson("/api/v1/orders/{$order->uuid}/checkout", [
            'payment_token' => 'pm_test_order',
            'provider' => 'stripe',
            'idempotency_key' => 'order-checkout-1',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.status', 'completed');

        $this->assertDatabaseHas('transactions', [
            'order_id' => $order->id,
            'amount' => 25.00,
        ]);

        Event::assertDispatched(OrderPaid::class);
    }

    #[Test]
    public function it_cancels_pending_order(): void
    {
        $order = Order::factory()->create([
            'status' => OrderStatus::Pending,
            'payment_status' => OrderPaymentStatus::Pending,
            'placed_at' => now(),
        ]);

        $response = $this->postJson("/api/v1/orders/{$order->uuid}/cancel", [
            'reason' => 'changed_mind',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    #[Test]
    public function it_lists_orders(): void
    {
        Order::factory()->count(2)->create();

        $this->getJson('/api/v1/orders')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }
}
