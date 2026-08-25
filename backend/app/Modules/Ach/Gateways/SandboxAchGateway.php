<?php

namespace App\Modules\Ach\Gateways;

use App\Modules\Ach\DTOs\AchTokenizeRequest;
use App\Modules\Ach\DTOs\AchTokenizeResponse;
use App\Modules\Ach\Enums\AchProviderType;
use App\Modules\Ach\Support\AchValidator;
use App\Modules\Payment\Enums\PaymentProvider;

final class SandboxAchGateway extends AbstractAchGateway
{
    public function provider(): PaymentProvider
    {
        return PaymentProvider::SandboxAch;
    }

    protected function providerType(): AchProviderType
    {
        return AchProviderType::Sandbox;
    }

    protected function performTokenize(AchTokenizeRequest $request): AchTokenizeResponse
    {
        $this->rejectFailedTestRouting($request);

        return new AchTokenizeResponse(
            paymentToken: $this->makePaymentToken(),
            last4: AchValidator::last4($request->accountNumber),
            accountType: $request->accountType,
            holderType: $request->holderType,
            provider: AchProviderType::Sandbox,
            authorizationId: 'auth_sandbox_'.substr(md5($request->routingNumber), 0, 8),
            raw: ['sandbox' => true],
        );
    }
}
