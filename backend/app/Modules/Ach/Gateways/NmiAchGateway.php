<?php

namespace App\Modules\Ach\Gateways;

use App\Modules\Ach\DTOs\AchTokenizeRequest;
use App\Modules\Ach\DTOs\AchTokenizeResponse;
use App\Modules\Ach\Enums\AchProviderType;
use App\Modules\Ach\Support\AchValidator;
use App\Modules\Payment\Enums\PaymentProvider;
use Illuminate\Support\Str;

final class NmiAchGateway extends AbstractAchGateway
{
    public function provider(): PaymentProvider
    {
        return PaymentProvider::NmiAch;
    }

    protected function providerType(): AchProviderType
    {
        return AchProviderType::NmiAch;
    }

    protected function performTokenize(AchTokenizeRequest $request): AchTokenizeResponse
    {
        $this->rejectFailedTestRouting($request);

        return new AchTokenizeResponse(
            paymentToken: 'tok_nmi_ach_'.Str::uuid(),
            last4: AchValidator::last4($request->accountNumber),
            accountType: $request->accountType,
            holderType: $request->holderType,
            provider: AchProviderType::NmiAch,
            authorizationId: 'auth_nmi_'.Str::random(10),
            raw: ['provider' => 'nmi_ach', 'sandbox' => (bool) ($this->config['sandbox_mode'] ?? true)],
        );
    }
}
