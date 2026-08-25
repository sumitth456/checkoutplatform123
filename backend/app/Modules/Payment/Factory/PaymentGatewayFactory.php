<?php

namespace App\Modules\Payment\Factory;

use App\Models\PaymentGatewayConfig;
use App\Modules\Payment\Contracts\IPaymentGateway;
use App\Modules\Payment\Decorators\ResilientGatewayDecorator;
use App\Modules\Payment\Enums\PaymentProvider;
use App\Modules\Payment\Gateways\AuthorizeNetGateway;
use App\Modules\Payment\Gateways\NMIGateway;
use App\Modules\Payment\Gateways\PayPalGateway;
use App\Modules\Payment\Gateways\StripeGateway;
use App\Modules\Payment\Resilience\CircuitBreaker;
use InvalidArgumentException;

class PaymentGatewayFactory
{
    public function __construct(
        private readonly CircuitBreaker $circuitBreaker,
    ) {}

    public function getGateway(PaymentProvider $provider, bool $resilient = true): IPaymentGateway
    {
        $gateway = match ($provider) {
            PaymentProvider::Stripe => new StripeGateway($this->providerConfig('stripe')),
            PaymentProvider::PayPal => new PayPalGateway($this->providerConfig('paypal')),
            PaymentProvider::AuthorizeNet => new AuthorizeNetGateway($this->providerConfig('authorize_net')),
            PaymentProvider::Nmi => new NMIGateway($this->providerConfig('nmi')),
        };

        if ($resilient) {
            return new ResilientGatewayDecorator($gateway, $this->circuitBreaker);
        }

        return $gateway;
    }

    private function providerConfig(string $provider): array
    {
        $config = config("payment.providers.{$provider}");

        if (! is_array($config)) {
            throw new InvalidArgumentException("Payment provider [{$provider}] is not configured.");
        }

        $dbGateway = PaymentGatewayConfig::query()
            ->where('provider', $provider)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->first();

        if ($dbGateway === null) {
            return $config;
        }

        $credentials = array_filter($dbGateway->credentials ?? []);

        return array_merge($config, $credentials);
    }
}
