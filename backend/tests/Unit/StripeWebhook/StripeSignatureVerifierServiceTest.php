<?php

namespace Tests\Unit\StripeWebhook;

use App\Modules\StripeWebhook\Exceptions\StripeWebhookException;
use App\Modules\StripeWebhook\Services\StripeSignatureVerifierService;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StripeSignatureVerifierServiceTest extends TestCase
{
    #[Test]
    public function it_verifies_valid_stripe_signatures(): void
    {
        config([
            'stripe_webhook.verify_signature' => true,
            'stripe_webhook.webhook_secret' => 'whsec_test_secret',
            'stripe_webhook.tolerance_seconds' => 300,
        ]);

        $payload = '{"id":"evt_sig","type":"payment_intent.succeeded"}';
        $timestamp = time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", 'whsec_test_secret');

        $request = Request::create('/webhooks/stripe', 'POST', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        app(StripeSignatureVerifierService::class)->verify($request);

        $this->assertTrue(true);
    }

    #[Test]
    public function it_rejects_invalid_signatures(): void
    {
        config([
            'stripe_webhook.verify_signature' => true,
            'stripe_webhook.webhook_secret' => 'whsec_test_secret',
        ]);

        $request = Request::create('/webhooks/stripe', 'POST', content: '{"id":"evt_bad"}');
        $request->headers->set('Stripe-Signature', 't='.time().',v1=invalid');

        $this->expectException(StripeWebhookException::class);

        app(StripeSignatureVerifierService::class)->verify($request);
    }
}
