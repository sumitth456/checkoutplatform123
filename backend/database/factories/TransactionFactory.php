<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'customer_id' => Customer::factory(),
            'type' => 'capture',
            'status' => 'captured',
            'currency' => 'USD',
            'amount' => fake()->randomFloat(2, 10, 200),
            'processed_at' => now(),
        ];
    }

    public function forOrder(Order $order): static
    {
        return $this->state(fn () => [
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'currency' => $order->currency,
        ]);
    }

    public function refund(float $amount): static
    {
        return $this->state(fn () => [
            'type' => 'refund',
            'status' => 'refunded',
            'amount' => $amount,
            'processed_at' => now(),
        ]);
    }

    public function failed(float $amount): static
    {
        return $this->state(fn () => [
            'type' => 'capture',
            'status' => 'failed',
            'amount' => $amount,
            'processed_at' => now(),
            'failure_code' => 'card_declined',
            'failure_message' => 'Your card was declined.',
        ]);
    }
}
