<?php

namespace App\Modules\Payment\Routing;

use App\Models\PaymentGatewayConfig;
use App\Modules\Payment\Contracts\GatewayRoutingStrategyInterface;
use App\Modules\Payment\DTOs\PaymentRequest;
use App\Modules\Payment\Enums\PaymentProvider;

class ConfigGatewayRoutingStrategy implements GatewayRoutingStrategyInterface
{
    public function resolve(PaymentRequest $request, ?PaymentProvider $preferred = null): PaymentProvider
    {
        if ($preferred !== null) {
            return $preferred;
        }

        if ($request->provider !== null) {
            return $request->provider;
        }

        $configuredDefault = PaymentGatewayConfig::query()
            ->where('is_active', true)
            ->where('is_default', true)
            ->value('provider');

        if ($configuredDefault !== null) {
            return PaymentProvider::from($configuredDefault);
        }

        return PaymentProvider::from(config('payment.default_provider', PaymentProvider::Stripe->value));
    }
}
