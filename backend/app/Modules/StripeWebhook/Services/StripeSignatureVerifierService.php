<?php

namespace App\Modules\StripeWebhook\Services;

use App\Modules\StripeWebhook\Exceptions\StripeWebhookException;
use Illuminate\Http\Request;

class StripeSignatureVerifierService
{
    public function verify(Request $request): void
    {
        if (! config('stripe_webhook.verify_signature', true)) {
            return;
        }

        $secret = config('stripe_webhook.webhook_secret');

        if (blank($secret)) {
            throw StripeWebhookException::invalidPayload('Webhook secret is not configured.');
        }

        $signatureHeader = (string) $request->header('Stripe-Signature');

        if ($signatureHeader === '') {
            throw StripeWebhookException::invalidSignature();
        }

        $payload = $request->getContent();
        $timestamp = $this->extractTimestamp($signatureHeader);

        if ($timestamp === null) {
            throw StripeWebhookException::invalidSignature();
        }

        $tolerance = (int) config('stripe_webhook.tolerance_seconds', 300);

        if (abs(time() - $timestamp) > $tolerance) {
            throw StripeWebhookException::invalidSignature();
        }

        $expected = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);
        $signatures = $this->extractSignatures($signatureHeader);
        $verified = false;

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                $verified = true;
                break;
            }
        }

        if (! $verified) {
            throw StripeWebhookException::invalidSignature();
        }
    }

    private function extractTimestamp(string $header): ?int
    {
        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);

            if ($key === 't' && is_numeric($value)) {
                return (int) $value;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function extractSignatures(string $header): array
    {
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);

            if (in_array($key, ['v1', 'v0'], true) && filled($value)) {
                $signatures[] = $value;
            }
        }

        return $signatures;
    }
}
