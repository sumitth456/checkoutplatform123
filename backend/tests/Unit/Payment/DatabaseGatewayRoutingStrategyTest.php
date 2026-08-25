<?php

namespace Tests\Unit\Payment;

use App\Models\PaymentGatewayConfig;
use App\Modules\Payment\DTOs\PaymentRequest;
use App\Modules\Payment\Enums\PaymentProvider;
use App\Modules\Payment\Routing\DatabaseGatewayRoutingStrategy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DatabaseGatewayRoutingStrategyTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_honors_preferred_provider_on_request(): void
    {
        PaymentGatewayConfig::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Stripe',
            'slug' => 'stripe-routing',
            'provider' => 'stripe',
            'is_active' => true,
            'is_default' => true,
        ]);

        $request = new PaymentRequest(
            amount: '10.00',
            currency: 'USD',
            paymentToken: 'pm_test',
            customerId: '1',
            provider: PaymentProvider::PayPal,
        );

        $resolved = app(DatabaseGatewayRoutingStrategy::class)->resolve($request, PaymentProvider::Stripe);

        $this->assertSame(PaymentProvider::Stripe, $resolved);
    }
}
