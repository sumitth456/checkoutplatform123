<?php

namespace Tests\Unit\Payment;

use App\Modules\Payment\DTOs\PaymentRequest;
use App\Modules\Payment\Enums\PaymentProvider;
use App\Modules\Payment\Enums\PaymentStatus;
use App\Modules\Payment\Gateways\StripeGateway;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StripeGatewayTest extends TestCase
{
    #[Test]
    public function it_authorizes_with_manual_capture(): void
    {
        Http::fake([
            'api.stripe.com/*' => Http::response([
                'id' => 'pi_gateway_auth',
                'client_secret' => 'pi_gateway_auth_secret',
                'status' => 'requires_capture',
            ], 200),
        ]);

        $gateway = new StripeGateway(config('payment.providers.stripe'));

        $response = $gateway->authorize(new PaymentRequest(
            amount: '99.99',
            currency: 'USD',
            paymentToken: 'pm_gateway',
        ));

        $this->assertSame(PaymentStatus::Authorized, $response->status);
        $this->assertSame(PaymentProvider::Stripe, $response->provider);
        $this->assertSame('pi_gateway_auth', $response->transactionId);

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), '/payment_intents')
                && $request['capture_method'] === 'manual'
                && $request['amount'] === 9999;
        });
    }

    #[Test]
    public function it_maps_gateway_status_responses(): void
    {
        Http::fake([
            'api.stripe.com/*' => Http::response([
                'id' => 'pi_gateway_status',
                'status' => 'canceled',
            ], 200),
        ]);

        $status = (new StripeGateway(config('payment.providers.stripe')))
            ->getStatus('pi_gateway_status');

        $this->assertSame(PaymentStatus::Voided, $status);
    }

    #[Test]
    public function it_throws_on_transport_failure(): void
    {
        Http::fake([
            'api.stripe.com/*' => Http::response(['message' => 'invalid request'], 400),
        ]);

        $this->expectException(\App\Modules\Payment\Exceptions\PaymentGatewayException::class);

        (new StripeGateway(config('payment.providers.stripe')))->capture(
            transactionId: 'pi_fail',
            amount: '10.00',
            currency: 'USD',
        );
    }
}
