<?php

namespace Tests\Unit\Payment;

use App\Modules\Payment\DTOs\PaymentRequest;
use App\Modules\Payment\DTOs\RefundRequest;
use App\Modules\Payment\Enums\LedgerEntryType;
use App\Modules\Payment\Enums\PaymentProvider;
use App\Modules\Payment\Enums\PaymentStatus;
use App\Modules\Payment\Services\PaymentOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentOrchestratorCaptureTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_captures_payment_and_records_ledger(): void
    {
        Http::fake([
            'api.stripe.com/*' => Http::response([
                'id' => 'pi_capture_unit',
                'status' => 'succeeded',
            ], 200),
        ]);

        $response = app(PaymentOrchestrator::class)->capture(
            transactionId: 'pi_capture_unit',
            amount: '30.00',
            currency: 'USD',
            provider: PaymentProvider::Stripe->value,
            idempotencyKey: 'capture-idem-1',
        );

        $this->assertSame(PaymentStatus::Captured, $response->status);
        $this->assertSame('pi_capture_unit', $response->transactionId);

        $this->assertDatabaseHas('payment_ledger_entries', [
            'entry_type' => LedgerEntryType::Capture->value,
            'status_to' => PaymentStatus::Captured->value,
            'idempotency_key' => 'capture-idem-1',
        ]);
    }

    #[Test]
    public function it_refunds_payment_and_records_ledger(): void
    {
        Http::fake([
            'api.stripe.com/*' => Http::response([
                'id' => 're_refund_unit',
                'status' => 'succeeded',
            ], 200),
        ]);

        $response = app(PaymentOrchestrator::class)->processRefund(new RefundRequest(
            transactionId: 'pi_refund_unit',
            amount: '12.50',
            currency: 'USD',
            provider: PaymentProvider::Stripe,
            idempotencyKey: 'refund-idem-1',
        ));

        $this->assertSame(PaymentStatus::Refunded, $response->status);

        $this->assertDatabaseHas('payment_ledger_entries', [
            'entry_type' => LedgerEntryType::Refund->value,
            'status_to' => PaymentStatus::Refunded->value,
            'idempotency_key' => 'refund-idem-1',
        ]);
    }

    #[Test]
    public function it_voids_payment_and_records_ledger(): void
    {
        Http::fake([
            'api.stripe.com/*' => Http::response([
                'id' => 'pi_void_unit',
                'status' => 'canceled',
            ], 200),
        ]);

        $response = app(PaymentOrchestrator::class)->voidTransaction(
            transactionId: 'pi_void_unit',
            provider: PaymentProvider::Stripe->value,
            idempotencyKey: 'void-idem-1',
        );

        $this->assertSame(PaymentStatus::Voided, $response->status);

        $this->assertDatabaseHas('payment_ledger_entries', [
            'entry_type' => LedgerEntryType::Void->value,
            'status_to' => PaymentStatus::Voided->value,
            'idempotency_key' => 'void-idem-1',
        ]);
    }

    #[Test]
    public function it_returns_cached_capture_response_for_duplicate_idempotency_key(): void
    {
        Http::fake([
            'api.stripe.com/*' => Http::response([
                'id' => 'pi_capture_cached',
                'status' => 'succeeded',
            ], 200),
        ]);

        $orchestrator = app(PaymentOrchestrator::class);

        $first = $orchestrator->capture(
            transactionId: 'pi_capture_cached',
            amount: '20.00',
            currency: 'USD',
            provider: PaymentProvider::Stripe->value,
            idempotencyKey: 'capture-dup',
        );

        $second = $orchestrator->capture(
            transactionId: 'pi_capture_cached',
            amount: '20.00',
            currency: 'USD',
            provider: PaymentProvider::Stripe->value,
            idempotencyKey: 'capture-dup',
        );

        $this->assertSame($first->transactionId, $second->transactionId);
        Http::assertSentCount(1);
    }
}
