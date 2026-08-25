<?php

namespace Tests\Unit\Payment;

use App\Modules\Payment\DTOs\PaymentRequest;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentRequestTest extends TestCase
{
    #[Test]
    public function it_rejects_raw_card_numbers_for_pci_scope(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Raw card numbers are not permitted');

        new PaymentRequest(
            amount: '10.00',
            currency: 'USD',
            paymentToken: '4111111111111111',
        );
    }

    #[Test]
    public function it_accepts_provider_payment_tokens(): void
    {
        $request = PaymentRequest::fromArray([
            'amount' => '49.99',
            'currency' => 'usd',
            'payment_token' => 'tok_visa_test',
            'idempotency_key' => 'idem-123',
        ]);

        $this->assertSame('49.99', $request->amount);
        $this->assertSame('USD', $request->currency);
        $this->assertSame('tok_visa_test', $request->paymentToken);
    }
}
