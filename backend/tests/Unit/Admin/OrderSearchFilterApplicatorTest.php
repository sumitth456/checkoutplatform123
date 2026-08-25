<?php

namespace Tests\Unit\Admin;

use App\Models\Campaign;
use App\Models\Customer;
use App\Models\Order;
use App\Modules\Admin\Services\OrderSearchFilterApplicator;
use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Order\Enums\OrderType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class OrderSearchFilterApplicatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_filters_by_order_number_and_customer_email(): void
    {
        $customer = Customer::factory()->create([
            'email' => 'search-test@example.com',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'phone' => '5551234567',
            'company' => 'Acme Corp',
        ]);

        $matching = Order::factory()->create([
            'customer_id' => $customer->id,
            'order_number' => 'ORD-SEARCH-001',
            'customer_email' => 'search-test@example.com',
            'customer_phone' => '5551234567',
            'ip_address' => '203.0.113.10',
            'status' => OrderStatus::Completed,
            'order_type' => OrderType::Initial,
        ]);

        Order::factory()->create([
            'order_number' => 'ORD-OTHER-002',
            'customer_email' => 'other@example.com',
        ]);

        $applicator = app(OrderSearchFilterApplicator::class);

        $byOrder = $applicator->apply(Order::query(), Request::create('/', 'GET', [
            'of_order_id' => 'ORD-SEARCH-001',
        ]));

        $this->assertSame(1, $byOrder->count());
        $this->assertTrue($byOrder->whereKey($matching->id)->exists());

        $byEmail = $applicator->apply(Order::query(), Request::create('/', 'GET', [
            'of_email' => 'search-test@example.com',
            'of_email_exact' => '1',
        ]));

        $this->assertSame(1, $byEmail->count());

        $byName = $applicator->apply(Order::query(), Request::create('/', 'GET', [
            'of_first_name' => 'Jane',
            'of_last_name' => 'Doe',
        ]));

        $this->assertSame(1, $byName->count());

        $byCompany = $applicator->apply(Order::query(), Request::create('/', 'GET', [
            'of_company' => 'Acme',
        ]));

        $this->assertSame(1, $byCompany->count());

        $byIp = $applicator->apply(Order::query(), Request::create('/', 'GET', [
            'of_ip' => '203.0.113',
        ]));

        $this->assertSame(1, $byIp->count());
    }

    public function test_filters_by_status_type_campaign_and_customer_id(): void
    {
        $campaign = Campaign::factory()->create();
        $customer = Customer::factory()->create();

        $matching = Order::factory()->create([
            'customer_id' => $customer->id,
            'platform_campaign_id' => $campaign->id,
            'status' => OrderStatus::Processing,
            'order_type' => OrderType::Rebill,
        ]);

        Order::factory()->create([
            'status' => OrderStatus::Completed,
            'order_type' => OrderType::Initial,
        ]);

        $applicator = app(OrderSearchFilterApplicator::class);

        $filtered = $applicator->apply(Order::query(), Request::create('/', 'GET', [
            'of_status' => OrderStatus::Processing->value,
            'of_order_type' => OrderType::Rebill->value,
            'of_campaign' => (string) $campaign->id,
            'of_customer_id' => (string) $customer->id,
        ]));

        $this->assertSame(1, $filtered->count());
        $this->assertTrue($filtered->whereKey($matching->id)->exists());
    }

    public function test_filters_by_state_country_and_currency(): void
    {
        $matching = Order::factory()->create([
            'currency' => 'USD',
            'country_code' => 'US',
            'shipping_address' => [
                'state' => 'California',
                'country' => 'US',
            ],
            'billing_address' => [
                'state' => 'California',
                'country' => 'US',
            ],
        ]);

        Order::factory()->create([
            'currency' => 'EUR',
            'country_code' => 'DE',
            'shipping_address' => [
                'state' => 'Berlin',
                'country' => 'DE',
            ],
        ]);

        $applicator = app(OrderSearchFilterApplicator::class);

        $byState = $applicator->apply(Order::query(), Request::create('/', 'GET', [
            'of_state' => 'California',
        ]));

        $this->assertSame(1, $byState->count());
        $this->assertTrue($byState->whereKey($matching->id)->exists());

        $byCountry = $applicator->apply(Order::query(), Request::create('/', 'GET', [
            'of_country' => 'US',
        ]));

        $this->assertSame(1, $byCountry->count());

        $byCurrency = $applicator->apply(Order::query(), Request::create('/', 'GET', [
            'of_currency' => 'USD',
        ]));

        $this->assertSame(1, $byCurrency->count());
    }
}
