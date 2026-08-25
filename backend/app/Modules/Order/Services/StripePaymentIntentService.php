<?php

namespace App\Modules\Order\Services;

use App\Modules\Order\DTOs\PaymentIntentDTO;
use App\Modules\Payment\Enums\PaymentProvider;
use App\Modules\Payment\Exceptions\PaymentGatewayException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class StripePaymentIntentService
{
    public function create(
        string $amount,
        string $currency,
        array $metadata = [],
    ): PaymentIntentDTO {
        $response = $this->httpClient()->post('/payment_intents', [
            'amount' => $this->toMinorUnits($amount),
            'currency' => strtolower($currency),
            'capture_method' => 'manual',
            'metadata' => $this->stringifyMetadata($metadata),
        ]);

        if ($response->failed()) {
            $body = $response->json() ?? [];

            throw PaymentGatewayException::transportFailure(
                provider: PaymentProvider::Stripe,
                message: (string) ($body['error']['message'] ?? $response->body()),
                context: ['action' => 'create_payment_intent', 'status' => $response->status()],
            );
        }

        return $this->mapIntent($response->json() ?? [], $amount, $currency);
    }

    public function confirm(string $paymentIntentId, string $paymentMethod): array
    {
        $response = $this->httpClient()->post("/payment_intents/{$paymentIntentId}/confirm", [
            'payment_method' => $paymentMethod,
        ]);

        if ($response->failed()) {
            $body = $response->json() ?? [];

            throw PaymentGatewayException::transportFailure(
                provider: PaymentProvider::Stripe,
                message: (string) ($body['error']['message'] ?? $response->body()),
                context: [
                    'action' => 'confirm_payment_intent',
                    'payment_intent_id' => $paymentIntentId,
                    'status' => $response->status(),
                ],
            );
        }

        return $response->json() ?? [];
    }

    public function retrieve(string $paymentIntentId): PaymentIntentDTO
    {
        $response = $this->httpClient()->get("/payment_intents/{$paymentIntentId}");

        if ($response->failed()) {
            throw PaymentGatewayException::transportFailure(
                provider: PaymentProvider::Stripe,
                message: $response->json('error.message') ?? $response->body(),
                context: ['action' => 'retrieve_payment_intent', 'payment_intent_id' => $paymentIntentId],
            );
        }

        $body = $response->json() ?? [];
        $amount = number_format(((int) ($body['amount'] ?? 0)) / 100, 2, '.', '');
        $currency = strtoupper((string) ($body['currency'] ?? 'USD'));

        return $this->mapIntent($body, $amount, $currency);
    }

    private function mapIntent(array $body, string $amount, string $currency): PaymentIntentDTO
    {
        return new PaymentIntentDTO(
            id: (string) ($body['id'] ?? 'pi_'.Str::uuid()),
            clientSecret: (string) ($body['client_secret'] ?? ''),
            status: (string) ($body['status'] ?? 'requires_payment_method'),
            amount: $amount,
            currency: $currency,
            raw: $body,
        );
    }

    private function httpClient(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('payment.providers.stripe.base_url', 'https://api.stripe.com/v1'), '/'))
            ->timeout((int) config('payment.resilience.timeout_seconds', 30))
            ->acceptJson()
            ->asJson()
            ->withToken((string) config('payment.providers.stripe.secret_key', 'sk_test'));
    }

    /** @param array<string, mixed> $metadata */
    private function stringifyMetadata(array $metadata): array
    {
        $normalized = [];

        foreach ($metadata as $key => $value) {
            if ($value === null) {
                continue;
            }

            $normalized[(string) $key] = is_scalar($value) ? (string) $value : json_encode($value);
        }

        return $normalized;
    }

    private function toMinorUnits(string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }
}
