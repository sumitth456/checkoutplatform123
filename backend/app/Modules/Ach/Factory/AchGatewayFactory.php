<?php

namespace App\Modules\Ach\Factory;

use App\Modules\Ach\Enums\AchProviderStatus;
use App\Models\AchProvider;
use App\Modules\Ach\Enums\AchProviderType;
use App\Modules\Ach\Gateways\AuthorizeNetEcheckGateway;
use App\Modules\Ach\Gateways\NmiAchGateway;
use App\Modules\Ach\Gateways\SandboxAchGateway;
use App\Modules\Ach\Gateways\StripeAchGateway;
use App\Modules\Ach\Gateways\UsaEpayAchGateway;
use App\Modules\Payment\Contracts\PaymentGatewayInterface;
use InvalidArgumentException;

final class AchGatewayFactory
{
    public function forProvider(AchProvider $provider): PaymentGatewayInterface
    {
        return $this->make(AchProviderType::from($provider->provider), $this->configFromModel($provider));
    }

    public function make(AchProviderType $type, array $config = []): PaymentGatewayInterface
    {
        $base = config("ach.providers.{$type->value}", []);

        $config = array_merge(is_array($base) ? $base : [], $config);

        return match ($type) {
            AchProviderType::Sandbox => new SandboxAchGateway($config),
            AchProviderType::StripeAch => new StripeAchGateway($config),
            AchProviderType::AuthorizeNetEcheck => new AuthorizeNetEcheckGateway($config),
            AchProviderType::NmiAch => new NmiAchGateway($config),
            AchProviderType::UsaEpayAch => new UsaEpayAchGateway($config),
        };
    }

    public function defaultGateway(): PaymentGatewayInterface
    {
        $provider = AchProvider::query()
            ->where('status', AchProviderStatus::Enabled)
            ->orderByDesc('is_default')
            ->orderBy('priority')
            ->first();

        if ($provider !== null) {
            return $this->forProvider($provider);
        }

        return $this->make(AchProviderType::Sandbox, ['sandbox_mode' => config('ach.sandbox_mode', true)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function configFromModel(AchProvider $provider): array
    {
        return array_filter([
            'api_key' => $provider->api_key,
            'secret_key' => $provider->secret_key,
            'merchant_id' => $provider->merchant_id,
            'sandbox_mode' => $provider->sandbox_mode,
            'settings' => $provider->settings,
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }
}
