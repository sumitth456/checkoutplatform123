<?php

namespace App\Modules\Admin\Services;

use App\Models\PaymentGatewayConfig;
use App\Modules\Payment\Enums\PaymentProvider;

class GatewayRoutingAdminService
{
    /** @var array<string, mixed>|null */
    private ?array $cachedRoutingPlan = null;

    public function __construct(
        private readonly PaymentGatewayLoadService $load,
        private readonly PlatformConfigResolver $platformConfig,
    ) {}

    /**
     * @return array{
     *     selected: ?PaymentGatewayConfig,
     *     hops: list<array<string, mixed>>,
     *     failover_steps: list<array<string, mixed>>
     * }
     */
    public function routingPlan(): array
    {
        if ($this->cachedRoutingPlan !== null) {
            return $this->cachedRoutingPlan;
        }

        $gateways = PaymentGatewayConfig::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderByDesc('weight')
            ->get();

        $selected = null;
        $hops = [];
        $rank = 1;

        foreach ($gateways as $gateway) {
            $stats = $this->load->stats($gateway);
            $withinLimits = $this->load->isWithinLimits($gateway);

            if ($selected === null && $withinLimits) {
                $status = 'selected';
                $selected = $gateway;
                $reason = null;
            } elseif (! $withinLimits) {
                $status = 'skipped';
                $reason = 'Daily or monthly volume limit reached.';
            } else {
                $status = 'standby';
                $reason = null;
            }

            $hops[] = [
                'rank' => $rank++,
                'gateway_id' => $gateway->id,
                'name' => $gateway->name,
                'provider' => $gateway->provider,
                'status' => $status,
                'reason' => $reason,
                'stats' => $stats,
            ];
        }

        $failoverSteps = [];

        if ($selected === null) {
            $default = PaymentGatewayConfig::query()
                ->where('is_active', true)
                ->where('is_default', true)
                ->first();

            if ($default !== null) {
                $selected = $default;
                $failoverSteps[] = [
                    'type' => 'default_gateway',
                    'gateway' => $default,
                ];
            } else {
                $failoverSteps[] = [
                    'type' => 'config_fallback',
                    'provider' => $this->platformConfig->defaultPaymentProvider()->value,
                ];
            }
        }

        return $this->cachedRoutingPlan = [
            'selected' => $selected,
            'hops' => $hops,
            'failover_steps' => $failoverSteps,
        ];
    }

    /**
     * @return array{label: string, color: string}
     */
    public function roleForGateway(PaymentGatewayConfig $gateway): array
    {
        if (! $gateway->is_active) {
            return [
                'label' => 'Inactive',
                'color' => 'gray',
            ];
        }

        $plan = $this->routingPlan();

        if ($plan['selected']?->id === $gateway->id) {
            return [
                'label' => 'Selected',
                'color' => 'success',
            ];
        }

        foreach ($plan['hops'] as $hop) {
            if (($hop['gateway_id'] ?? null) !== $gateway->id) {
                continue;
            }

            return match ($hop['status']) {
                'skipped' => ['label' => 'Skipped', 'color' => 'danger'],
                'standby' => ['label' => 'Standby', 'color' => 'warning'],
                default => ['label' => 'Standby', 'color' => 'gray'],
            };
        }

        return [
            'label' => 'Standby',
            'color' => 'gray',
        ];
    }

    /**
     * @return list<int>
     */
    public function orderedGatewayIds(): array
    {
        return PaymentGatewayConfig::query()
            ->orderBy('sort_order')
            ->orderByDesc('weight')
            ->pluck('id')
            ->all();
    }

    /**
     * @param  list<int>  $orderedIds
     */
    public function applyPriorityOrder(array $orderedIds): void
    {
        foreach ($orderedIds as $index => $id) {
            PaymentGatewayConfig::query()->whereKey($id)->update([
                'sort_order' => $index + 1,
            ]);
        }

        $this->cachedRoutingPlan = null;
    }

    public function moveGateway(PaymentGatewayConfig $gateway, int $direction): bool
    {
        $ids = $this->orderedGatewayIds();
        $index = array_search($gateway->id, $ids, true);

        if ($index === false) {
            return false;
        }

        $targetIndex = $index + $direction;

        if ($targetIndex < 0 || $targetIndex >= count($ids)) {
            return false;
        }

        [$ids[$index], $ids[$targetIndex]] = [$ids[$targetIndex], $ids[$index]];

        $this->applyPriorityOrder($ids);

        return true;
    }
}
