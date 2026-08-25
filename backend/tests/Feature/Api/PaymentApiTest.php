<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_authorizes_payment_via_api(): void
    {
        Http::fake([
            'api.stripe.com/*' => Http::response([
                'id' => 'pi_api_test',
                'client_secret' => 'secret',
                'status' => 'requires_capture',
            ], 200),
        ]);

        $response = $this->postJson('/api/v1/payments/authorize', [
            'amount' => 19.99,
            'currency' => 'USD',
            'payment_token' => 'pm_test_token',
            'provider' => 'stripe',
            'idempotency_key' => 'api-auth-1',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'authorized')
            ->assertJsonPath('data.transaction_id', 'pi_api_test')
            ->assertJsonPath('data.provider', 'stripe');
    }

    #[Test]
    public function it_rejects_raw_card_numbers_at_validation_layer(): void
    {
        $response = $this->postJson('/api/v1/payments/authorize', [
            'amount' => 19.99,
            'currency' => 'USD',
            'payment_token' => '4111111111111111',
            'idempotency_key' => 'api-auth-2',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['payment_token']);
    }

    #[Test]
    public function it_requires_idempotency_key_for_authorization(): void
    {
        $response = $this->postJson('/api/v1/payments/authorize', [
            'amount' => 19.99,
            'currency' => 'USD',
            'payment_token' => 'pm_test_token',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['idempotency_key']);
    }

    #[Test]
    public function it_returns_transaction_status(): void
    {
        Http::fake([
            'api.stripe.com/*' => Http::response([
                'id' => 'pi_status_api',
                'status' => 'requires_capture',
            ], 200),
        ]);

        $response = $this->getJson('/api/v1/payments/pi_status_api/status?provider=stripe');

        $response->assertOk()
            ->assertJsonPath('transaction_id', 'pi_status_api')
            ->assertJsonPath('status', 'authorized');
    }

    #[Test]
    public function it_captures_authorized_payment_via_api(): void
    {
        Http::fake([
            'api.stripe.com/*' => Http::response([
                'id' => 'pi_capture_api',
                'status' => 'succeeded',
            ], 200),
        ]);

        $response = $this->postJson('/api/v1/payments/capture', [
            'transaction_id' => 'pi_capture_api',
            'amount' => 19.99,
            'currency' => 'USD',
            'provider' => 'stripe',
            'idempotency_key' => 'api-capture-1',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'captured')
            ->assertJsonPath('data.transaction_id', 'pi_capture_api')
            ->assertJsonPath('data.amount', '19.99');
    }

    #[Test]
    public function it_refunds_payment_via_api(): void
    {
        Http::fake([
            'api.stripe.com/*' => Http::response([
                'id' => 're_refund_api',
                'status' => 'succeeded',
            ], 200),
        ]);

        $response = $this->postJson('/api/v1/payments/refund', [
            'transaction_id' => 'pi_refund_api',
            'amount' => 10.00,
            'currency' => 'USD',
            'provider' => 'stripe',
            'reason' => 'customer_request',
            'idempotency_key' => 'api-refund-1',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'refunded')
            ->assertJsonPath('data.transaction_id', 'pi_refund_api')
            ->assertJsonPath('data.amount', '10');
    }

    #[Test]
    public function it_voids_authorized_payment_via_api(): void
    {
        Http::fake([
            'api.stripe.com/*' => Http::response([
                'id' => 'pi_void_api',
                'status' => 'canceled',
            ], 200),
        ]);

        $response = $this->postJson('/api/v1/payments/void', [
            'transaction_id' => 'pi_void_api',
            'provider' => 'stripe',
            'idempotency_key' => 'api-void-1',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'voided')
            ->assertJsonPath('data.transaction_id', 'pi_void_api');
    }

    #[Test]
    public function it_validates_capture_payload(): void
    {
        $response = $this->postJson('/api/v1/payments/capture', [
            'transaction_id' => 'pi_missing_amount',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['amount', 'currency']);
    }

    #[Test]
    public function it_validates_refund_payload(): void
    {
        $response = $this->postJson('/api/v1/payments/refund', [
            'transaction_id' => 'pi_refund',
            'amount' => 0,
            'currency' => 'US',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['amount', 'currency']);
    }
}
