<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProviderWebhookApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_accepts_paypal_webhook_payload(): void
    {
        $this->postJson('/api/v1/webhooks/paypal', [
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => ['id' => 'CAP-TEST-1'],
        ])->assertOk();
    }

    #[Test]
    public function it_accepts_nmi_webhook_payload(): void
    {
        $this->postJson('/api/v1/webhooks/nmi', [
            'event_type' => 'transaction.sale.success',
            'transaction_id' => 'nmi_tx_1',
        ])->assertOk();
    }
}
