<?php

namespace Tests\Unit\Payment;

use App\Modules\Payment\DTOs\PaymentResponse;
use App\Modules\Payment\Enums\PaymentProvider;
use App\Modules\Payment\Enums\PaymentStatus;
use App\Modules\Payment\Repositories\IdempotencyRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IdempotencyRepositoryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_stores_and_retrieves_matching_idempotency_records(): void
    {
        $repository = app(IdempotencyRepository::class);
        $response = new PaymentResponse(
            status: PaymentStatus::Authorized,
            transactionId: 'pi_idem_repo',
            gatewayReference: 'ref',
            provider: PaymentProvider::Stripe,
            amount: '10.00',
            currency: 'USD',
        );

        $repository->store('key-1', 'authorize', 'hash-abc', $response);

        $cached = $repository->find('key-1', 'authorize', 'hash-abc');

        $this->assertNotNull($cached);
        $this->assertSame('pi_idem_repo', $cached->transactionId);
        $this->assertSame(PaymentStatus::Authorized, $cached->status);
    }

    #[Test]
    public function it_returns_null_for_mismatched_request_hash(): void
    {
        $repository = app(IdempotencyRepository::class);
        $response = new PaymentResponse(
            status: PaymentStatus::Captured,
            transactionId: 'pi_hash_mismatch',
            gatewayReference: 'ref',
            provider: PaymentProvider::Stripe,
            amount: '20.00',
            currency: 'USD',
        );

        $repository->store('key-2', 'capture', 'hash-one', $response);

        $this->assertNull($repository->find('key-2', 'capture', 'hash-two'));
    }
}
