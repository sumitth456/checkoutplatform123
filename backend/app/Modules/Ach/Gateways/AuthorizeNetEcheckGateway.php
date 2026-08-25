<?php

namespace App\Modules\Ach\Gateways;

use App\Modules\Ach\DTOs\AchTokenizeRequest;
use App\Modules\Ach\DTOs\AchTokenizeResponse;
use App\Modules\Ach\Enums\AchProviderType;
use App\Modules\Ach\Support\AchValidator;
use App\Modules\Payment\Enums\PaymentProvider;
use Illuminate\Support\Str;

final class AuthorizeNetEcheckGateway extends AbstractAchGateway
{
    public function provider(): PaymentProvider
    {
        return PaymentProvider::AuthorizeNetEcheck;
    }

    protected function providerType(): AchProviderType
    {
        return AchProviderType::AuthorizeNetEcheck;
    }

    protected function performTokenize(AchTokenizeRequest $request): AchTokenizeResponse
    {
        $this->rejectFailedTestRouting($request);

        return new AchTokenizeResponse(
            paymentToken: 'tok_authnet_echeck_'.Str::uuid(),
            last4: AchValidator::last4($request->accountNumber),
            accountType: $request->accountType,
            holderType: $request->holderType,
            provider: AchProviderType::AuthorizeNetEcheck,
            authorizationId: 'auth_authnet_'.Str::random(10),
            raw: [
                'provider' => 'authorize_net_echeck',
                'sandbox' => (bool) ($this->config['sandbox_mode'] ?? true),
            ],
        );
    }
}
