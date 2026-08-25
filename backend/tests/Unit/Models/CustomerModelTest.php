<?php

namespace Tests\Unit\Models;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_has_orders_and_transactions(): void
    {
        $customer = Customer::factory()->create();

        Order::factory()->create(['customer_id' => $customer->id]);
        Transaction::factory()->create(['customer_id' => $customer->id]);

        $this->assertCount(1, $customer->fresh()->orders);
        $this->assertCount(1, $customer->fresh()->transactions);
    }

    public function test_full_name_falls_back_to_email(): void
    {
        $customer = Customer::factory()->create([
            'first_name' => null,
            'last_name' => null,
            'email' => 'buyer@example.com',
        ]);

        $this->assertSame('buyer@example.com', $customer->full_name);
    }

    public function test_route_key_name_is_uuid(): void
    {
        $this->assertSame('uuid', (new Customer)->getRouteKeyName());
    }
}
