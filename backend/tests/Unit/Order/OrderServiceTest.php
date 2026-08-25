<?php

namespace Tests\Unit\Order;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Variant;
use App\Modules\Order\Enums\OrderPaymentStatus;
use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Order\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_requires_customer_email_when_customer_id_missing(): void
    {
        $this->expectException(ValidationException::class);

        app(OrderService::class)->place([
            'items' => [],
        ]);
    }

    #[Test]
    public function it_requires_at_least_one_line_item(): void
    {
        $this->expectException(ValidationException::class);

        app(OrderService::class)->place([
            'customer' => ['email' => 'buyer@example.com'],
            'items' => [],
        ]);
    }

    #[Test]
    public function it_rejects_unknown_variant_uuid(): void
    {
        try {
            app(OrderService::class)->place([
                'customer' => ['email' => 'buyer@example.com'],
                'items' => [
                    ['variant_uuid' => '00000000-0000-0000-0000-000000000000', 'quantity' => 1],
                ],
            ]);
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('items.0.variant_uuid', $exception->errors());

            return;
        }

        $this->fail('Expected ValidationException was not thrown.');
    }

    #[Test]
    public function it_places_order_with_correct_totals(): void
    {
        $variant = Variant::factory()->create([
            'price' => 20.00,
            'currency' => 'USD',
            'track_inventory' => false,
        ]);

        $order = app(OrderService::class)->place([
            'customer' => [
                'email' => 'totals@example.com',
                'first_name' => 'Total',
                'last_name' => 'Tester',
            ],
            'items' => [
                ['variant_uuid' => $variant->uuid, 'quantity' => 3],
            ],
        ]);

        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertSame(OrderPaymentStatus::Pending, $order->payment_status);
        $this->assertSame('60.00', (string) $order->grand_total);
        $this->assertCount(1, $order->items);
    }

    #[Test]
    public function it_rejects_cancelling_paid_orders(): void
    {
        $order = Order::factory()->create([
            'payment_status' => OrderPaymentStatus::Paid,
            'status' => OrderStatus::Completed,
            'placed_at' => now(),
        ]);

        $this->expectException(ValidationException::class);

        app(OrderService::class)->cancel($order);
    }

    #[Test]
    public function it_reuses_existing_customer_by_email(): void
    {
        $customer = Customer::factory()->create(['email' => 'repeat@example.com']);
        $variant = Variant::factory()->create([
            'track_inventory' => false,
            'price' => 10.00,
        ]);

        $order = app(OrderService::class)->place([
            'customer' => ['email' => 'repeat@example.com'],
            'items' => [
                ['variant_uuid' => $variant->uuid, 'quantity' => 1],
            ],
        ]);

        $this->assertSame($customer->id, $order->customer_id);
        $this->assertSame(1, Customer::query()->where('email', 'repeat@example.com')->count());
    }
}
