<?php

namespace App\Modules\Ach\Gateways;

use App\Modules\Ach\DTOs\AchTokenizeRequest;
use App\Modules\Ach\DTOs\AchTokenizeResponse;
use App\Modules\Ach\Enums\AchProviderType;
use App\Modules\Ach\Support\AchValidator;
use App\Modules\Payment\Enums\PaymentProvider;
use App\Modules\Payment\Exceptions\PaymentGatewayException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class StripeAchGateway extends AbstractAchGateway
{
    public function provider(): PaymentProvider
    {
        return PaymentProvider::StripeAch;
    }

    protected function providerType(): AchProviderType
    {
        return AchProviderType::StripeAch;
    }

    protected function performTokenize(AchTokenizeRequest $request): AchTokenizeResponse
    {
        if (blank($this->config['secret_key'] ?? null)) {
            throw PaymentGatewayException::transportFailure(
                provider: $this->provider(),
                message: 'Stripe ACH secret key is not configured.',
                context: ['action' => 'tokenize'],
            );
        }

        if ((bool) ($this->config['sandbox_mode'] ?? config('ach.sandbox_mode', true))) {
            $this->rejectFailedTestRouting($request);

            return new AchTokenizeResponse(
                paymentToken: 'tok_stripe_ach_'.Str::uuid(),
                last4: AchValidator::last4($request->accountNumber),
                accountType: $request->accountType,
                holderType: $request->holderType,
                provider: AchProviderType::StripeAch,
                authorizationId: 'auth_stripe_'.Str::random(12),
                raw: ['sandbox' => true, 'provider' => 'stripe_ach'],
            );
        }

        $response = Http::withToken((string) $this->config['secret_key'])
            ->asForm()
            ->post('https://api.stripe.com/v1/payment_methods', [
                'type' => 'us_bank_account',
                'billing_details[name]' => $request->accountHolderName,
                'us_bank_account[account_holder_type]' => $request->holderType->value,
                'us_bank_account[account_type]' => $request->accountType->value,
                'us_bank_account[routing_number]' => $request->routingNumber,
                'us_bank_account[account_number]' => $request->accountNumber,
            ]);

        if ($response->failed()) {
            throw PaymentGatewayException::transportFailure(
                provider: $this->provider(),
                message: $response->json('error.message') ?? 'Stripe ACH tokenization failed.',
                context: ['action' => 'tokenize', 'status' => $response->status()],
            );
        }

        $body = $response->json();

        return new AchTokenizeResponse(
            paymentToken: (string) ($body['id'] ?? $this->makePaymentToken()),
            last4: (string) ($body['us_bank_account']['last4'] ?? AchValidator::last4($request->accountNumber)),
            accountType: $request->accountType,
            holderType: $request->holderType,
            provider: AchProviderType::StripeAch,
            authorizationId: (string) ($body['id'] ?? null),
            raw: ['id' => $body['id'] ?? null],
        );
    }
}
