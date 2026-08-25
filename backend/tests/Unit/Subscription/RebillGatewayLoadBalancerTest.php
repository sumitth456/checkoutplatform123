<?php

namespace Tests\Unit\Subscription;

use App\Models\PaymentGatewayConfig;
use App\Models\Subscription;
use App\Modules\Subscription\Services\RebillGatewayLoadBalancer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RebillGatewayLoadBalancerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_orders_gateways_by_priority_and_rotates_on_retry(): void
    {
        PaymentGatewayConfig::query()->create([
            'name' => 'Stripe',
            'slug' => 'stripe-a',
            'provider' => 'stripe',
            'credentials' => ['secret_key' => 'sk_test'],
            'is_active' => true,
            'supports_subscriptions' => true,
            'failover_enabled' => true,
            'sort_order' => 1,
            'weight' => 100,
        ]);

        PaymentGatewayConfig::query()->create([
            'name' => 'PayPal',
            'slug' => 'paypal-a',
            'provider' => 'paypal',
            'credentials' => ['client_id' => 'x', 'client_secret' => 'y'],
            'is_active' => true,
            'supports_subscriptions' => true,
            'failover_enabled' => true,
            'sort_order' => 2,
            'weight' => 100,
        ]);

        $subscription = Subscription::factory()->create(['retry_count' => 0]);
        $balancer = app(RebillGatewayLoadBalancer::class);

        $firstAttempt = $balancer->gatewaysForCharge($subscription);
        $this->assertSame('stripe', $firstAttempt[0]->provider);

        $subscription->retry_count = 1;
        $retryAttempt = $balancer->gatewaysForCharge($subscription);
        $this->assertSame('paypal', $retryAttempt[0]->provider);
    }
}
