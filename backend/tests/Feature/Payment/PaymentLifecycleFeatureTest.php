<?php

namespace Tests\Feature\Payment;

use App\Modules\Payment\Enums\LedgerEntryType;
use App\Modules\Payment\Enums\PaymentProvider;
use App\Modules\Payment\Enums\PaymentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentLifecycleFeatureTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_completes_authorize_capture_refund_lifecycle(): void
    {
        Http::fake([
            'api.stripe.com/*' => Http::sequence()
                ->push([
                    'id' => 'pi_lifecycle',
                    'client_secret' => 'secret',
                    'status' => 'requires_capture',
                ], 200)
                ->push([
                    'id' => 'pi_lifecycle',
                    'status' => 'succeeded',
                ], 200)
                ->push([
                    'id' => 're_lifecycle',
                    'status' => 'succeeded',
                ], 200),
        ]);

        $authorize = $this->postJson('/api/v1/payments/authorize', [
            'amount' => 50.00,
            'currency' => 'USD',
            'payment_token' => 'pm_lifecycle',
            'provider' => 'stripe',
            'idempotency_key' => 'lifecycle-auth',
        ]);

        $authorize->assertCreated()
            ->assertJsonPath('data.status', PaymentStatus::Authorized->value)
            ->assertJsonPath('data.transaction_id', 'pi_lifecycle');

        $capture = $this->postJson('/api/v1/payments/capture', [
            'transaction_id' => 'pi_lifecycle',
            'amount' => 50.00,
            'currency' => 'USD',
            'provider' => 'stripe',
            'idempotency_key' => 'lifecycle-capture',
        ]);

        $capture->assertOk()
            ->assertJsonPath('data.status', PaymentStatus::Captured->value);

        $refund = $this->postJson('/api/v1/payments/refund', [
            'transaction_id' => 'pi_lifecycle',
            'amount' => 25.00,
            'currency' => 'USD',
            'provider' => 'stripe',
            'idempotency_key' => 'lifecycle-refund',
        ]);

        $refund->assertOk()
            ->assertJsonPath('data.status', PaymentStatus::Refunded->value);

        $this->assertDatabaseHas('payment_ledger_entries', [
            'provider' => PaymentProvider::Stripe->value,
            'entry_type' => LedgerEntryType::Authorize->value,
            'idempotency_key' => 'lifecycle-auth',
        ]);

        $this->assertDatabaseHas('payment_ledger_entries', [
            'entry_type' => LedgerEntryType::Capture->value,
        ]);

        $this->assertDatabaseHas('payment_ledger_entries', [
            'entry_type' => LedgerEntryType::Refund->value,
        ]);
    }

    #[Test]
    public function it_voids_authorized_payment_before_capture(): void
    {
        Http::fake([
            'api.stripe.com/*' => Http::sequence()
                ->push([
                    'id' => 'pi_void_flow',
                    'client_secret' => 'secret',
                    'status' => 'requires_capture',
                ], 200)
                ->push([
                    'id' => 'pi_void_flow',
                    'status' => 'canceled',
                ], 200),
        ]);

        $this->postJson('/api/v1/payments/authorize', [
            'amount' => 15.00,
            'currency' => 'USD',
            'payment_token' => 'pm_void_flow',
            'provider' => 'stripe',
            'idempotency_key' => 'void-flow-auth',
        ])->assertCreated();

        $this->postJson('/api/v1/payments/void', [
            'transaction_id' => 'pi_void_flow',
            'provider' => 'stripe',
            'idempotency_key' => 'void-flow-void',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', PaymentStatus::Voided->value);

        $this->assertDatabaseHas('payment_ledger_entries', [
            'entry_type' => LedgerEntryType::Void->value,
            'external_id' => 'pi_void_flow',
        ]);
    }
}
