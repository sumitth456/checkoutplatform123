<?php

namespace App\Modules\Payment\Routing;

use App\Models\PaymentGatewayConfig;
use App\Modules\Admin\Services\PlatformConfigResolver;
use App\Modules\Admin\Services\PaymentGatewayLoadService;
use App\Modules\Payment\Contracts\GatewayRoutingStrategyInterface;
use App\Modules\Payment\DTOs\PaymentRequest;
use App\Modules\Payment\Enums\PaymentProvider;
use Illuminate\Support\Collection;

class DatabaseGatewayRoutingStrategy implements GatewayRoutingStrategyInterface
{
    public function __construct(
        private readonly PaymentGatewayLoadService $load,
        private readonly PlatformConfigResolver $platformConfig,
    ) {}

    public function resolve(PaymentRequest $request, ?PaymentProvider $preferred = null): PaymentProvider
    {
        if ($preferred !== null) {
            return $preferred;
        }

        if ($request->provider !== null) {
            return $request->provider;
        }

        $candidate = $this->orderedActiveGateways()->first(function (PaymentGatewayConfig $gateway): bool {
            return $this->load->isWithinLimits($gateway);
        });

        if ($candidate !== null) {
            return PaymentProvider::from($candidate->provider);
        }

        $default = PaymentGatewayConfig::query()
            ->where('is_active', true)
            ->where('is_default', true)
            ->first();

        if ($default !== null) {
            return PaymentProvider::from($default->provider);
        }

        return $this->platformConfig->defaultPaymentProvider();
    }

    /**
     * @return Collection<int, PaymentGatewayConfig>
     */
    public function orderedActiveGateways(): Collection
    {
        return PaymentGatewayConfig::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderByDesc('weight')
            ->get();
    }
}
