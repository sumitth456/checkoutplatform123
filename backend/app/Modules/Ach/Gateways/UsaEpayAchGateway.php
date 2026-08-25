<?php

namespace App\Modules\Ach\Gateways;

use App\Modules\Ach\DTOs\AchTokenizeRequest;
use App\Modules\Ach\DTOs\AchTokenizeResponse;
use App\Modules\Ach\Enums\AchProviderType;
use App\Modules\Ach\Support\AchValidator;
use App\Modules\Payment\Enums\PaymentProvider;
use Illuminate\Support\Str;

final class UsaEpayAchGateway extends AbstractAchGateway
{
    public function provider(): PaymentProvider
    {
        return PaymentProvider::UsaEpayAch;
    }

    protected function providerType(): AchProviderType
    {
        return AchProviderType::UsaEpayAch;
    }

    protected function performTokenize(AchTokenizeRequest $request): AchTokenizeResponse
    {
        $this->rejectFailedTestRouting($request);

        return new AchTokenizeResponse(
            paymentToken: 'tok_usaepay_ach_'.Str::uuid(),
            last4: AchValidator::last4($request->accountNumber),
            accountType: $request->accountType,
            holderType: $request->holderType,
            provider: AchProviderType::UsaEpayAch,
            authorizationId: 'auth_usaepay_'.Str::random(10),
            raw: ['provider' => 'usaepay_ach', 'sandbox' => (bool) ($this->config['sandbox_mode'] ?? true)],
        );
    }
}
