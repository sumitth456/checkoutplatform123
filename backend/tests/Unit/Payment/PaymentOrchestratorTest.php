<?php

namespace Tests\Unit\Payment;

use App\Models\PaymentIdempotencyKey;
use App\Modules\Payment\DTOs\PaymentRequest;
use App\Modules\Payment\Enums\LedgerEntryType;
use App\Modules\Payment\Enums\PaymentProvider;
use App\Modules\Payment\Enums\PaymentStatus;
use App\Modules\Payment\Services\PaymentOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentOrchestratorTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_authorizes_through_gateway_and_records_ledger(): void
    {
        Http::fake([
            'api.stripe.com/*' => Http::response([
                'id' => 'pi_test_123',
                'client_secret' => 'pi_test_123_secret',
                'status' => 'requires_capture',
            ], 200),
        ]);

        $orchestrator = app(PaymentOrchestrator::class);

        $response = $orchestrator->processPayment(new PaymentRequest(
            amount: '25.00',
            currency: 'USD',
            paymentToken: 'pm_test_token',
            provider: PaymentProvider::Stripe,
            idempotencyKey: 'auth-idem-1',
        ));

        $this->assertSame(PaymentStatus::Authorized, $response->status);
        $this->assertSame('pi_test_123', $response->transactionId);
        $this->assertSame(PaymentProvider::Stripe, $response->provider);

        $this->assertDatabaseHas('payment_ledger_entries', [
            'provider' => PaymentProvider::Stripe->value,
            'entry_type' => LedgerEntryType::Authorize->value,
            'status_to' => PaymentStatus::Authorized->value,
            'idempotency_key' => 'auth-idem-1',
        ]);

        Http::assertSentCount(1);
    }

    #[Test]
    public function it_returns_cached_response_for_duplicate_idempotency_key(): void
    {
        Http::fake([
            'api.stripe.com/*' => Http::response([
                'id' => 'pi_cached',
                'client_secret' => 'secret',
                'status' => 'requires_capture',
            ], 200),
        ]);

        $orchestrator = app(PaymentOrchestrator::class);
        $request = new PaymentRequest(
            amount: '10.00',
            currency: 'USD',
            paymentToken: 'pm_test',
            provider: PaymentProvider::Stripe,
            idempotencyKey: 'duplicate-key',
        );

        $first = $orchestrator->processPayment($request);
        $second = $orchestrator->processPayment($request);

        $this->assertSame($first->transactionId, $second->transactionId);
        $this->assertSame(1, PaymentIdempotencyKey::query()->count());
        Http::assertSentCount(1);
    }

    #[Test]
    public function it_resolves_status_from_gateway(): void
    {
        Http::fake([
            'api.stripe.com/*' => Http::response([
                'id' => 'pi_status',
                'status' => 'succeeded',
            ], 200),
        ]);

        $status = app(PaymentOrchestrator::class)->getStatus('pi_status', PaymentProvider::Stripe->value);

        $this->assertSame(PaymentStatus::Captured, $status);
    }
}
