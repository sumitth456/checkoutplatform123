<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 20, 500);

        return [
            'uuid' => (string) Str::uuid(),
            'order_number' => strtoupper(fake()->unique()->bothify('ORD-#####')),
            'customer_id' => Customer::factory(),
            'status' => 'completed',
            'payment_status' => 'paid',
            'fraud_status' => 'clear',
            'fulfillment_status' => 'fulfilled',
            'currency' => 'USD',
            'subtotal' => $subtotal,
            'grand_total' => $subtotal,
            'refunded_total' => 0,
            'placed_at' => now(),
            'paid_at' => now(),
        ];
    }

    public function refunded(float $amount): static
    {
        return $this->state(fn () => [
            'refunded_total' => $amount,
            'payment_status' => 'refunded',
        ]);
    }
}
