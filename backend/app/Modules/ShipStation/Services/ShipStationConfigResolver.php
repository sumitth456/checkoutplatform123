<?php

namespace App\Modules\ShipStation\Services;

use App\Models\ShipStationSetting;
use App\Modules\ShipStation\Enums\ShipStationMode;
use App\Modules\ShipStation\Exceptions\ShipStationApiException;
use App\Modules\ShipStation\Support\ShipStationStoreWarehouseResolver;

class ShipStationConfigResolver
{
    public function __construct(
        private readonly ShipStationStoreWarehouseResolver $storeWarehouseResolver,
    ) {}

    public function mode(): ShipStationMode
    {
        return ShipStationMode::fromConfig();
    }

    public function isMockMode(): bool
    {
        return $this->mode()->isMock();
    }

    public function isRealMode(): bool
    {
        return $this->mode()->isReal();
    }

    /**
     * @return array{base_url: string, api_key: string, api_secret: string, timeout_seconds: int, is_active: bool, mode: string}
     */
    public function resolve(?ShipStationSetting $setting = null, bool $envOnly = false): array
    {
        if ($envOnly) {
            return $this->resolveFromEnvironment();
        }

        $setting ??= ShipStationSetting::current();
        $config = config('shipstation');

        $defaultBaseUrl = $this->isMockMode()
            ? (string) ($config['mock_base_url'] ?? 'https://docs.shipstation.com/_mock/apis/openapi')
            : (string) ($config['base_url'] ?? 'https://api.shipstation.com/v2');

        $baseUrl = $this->resolveBaseUrl(
            settingBaseUrl: $setting?->base_url,
            defaultBaseUrl: $defaultBaseUrl,
        );

        return [
            'mode' => $this->mode()->value,
            'base_url' => $baseUrl,
            'api_key' => (string) ($setting->api_key ?: ($config['api_key'] ?? '')),
            'api_secret' => (string) ($setting->api_secret ?: ($config['api_secret'] ?? '')),
            'timeout_seconds' => (int) ($setting->timeout_seconds ?: ($config['timeout_seconds'] ?? 30)),
            'is_active' => (bool) $setting->is_active,
        ];
    }

    /**
     * @return array{base_url: string, api_key: string, api_secret: string, timeout_seconds: int, is_active: bool, mode: string}
     */
    public function resolveFromEnvironment(): array
    {
        $config = config('shipstation');

        $defaultBaseUrl = $this->isMockMode()
            ? (string) ($config['mock_base_url'] ?? 'https://docs.shipstation.com/_mock/apis/openapi')
            : (string) ($config['base_url'] ?? 'https://api.shipstation.com/v2');

        return [
            'mode' => $this->mode()->value,
            'base_url' => $defaultBaseUrl,
            'api_key' => (string) ($config['api_key'] ?? ''),
            'api_secret' => (string) ($config['api_secret'] ?? ''),
            'timeout_seconds' => (int) ($config['timeout_seconds'] ?? 30),
            'is_active' => true,
        ];
    }

    public function isEnabled(): bool
    {
        $resolved = $this->resolve();

        if (! $resolved['is_active']) {
            return false;
        }

        if ($this->isMockMode()) {
            return true;
        }

        return filled($resolved['api_key']);
    }

    public function warehouseId(): ?string
    {
        if ($this->isMockMode()) {
            $id = config('shipstation.mock_warehouse_id');

            return filled($id) ? (string) $id : null;
        }

        $id = config('shipstation.warehouse_id');

        return filled($id) ? (string) $id : null;
    }

    /**
     * Warehouse for V2 shipment create for the configured (or given) store_id.
     */
    public function warehouseIdForStore(?string $storeId = null): ?string
    {
        $storeId ??= $this->storeId();

        if ($this->isMockMode()) {
            $global = $this->warehouseId();

            try {
                return $this->storeWarehouseResolver->resolveWarehouseId(
                    $storeId,
                    $global,
                    (bool) config('shipstation.store_warehouse_fallback_to_global', true),
                );
            } catch (ShipStationApiException) {
                return $global;
            }
        }

        return $this->storeWarehouseResolver->resolveWarehouseId(
            $storeId,
            $this->warehouseId(),
            (bool) config('shipstation.store_warehouse_fallback_to_global', true),
        );
    }

    public function storeId(): ?string
    {
        $id = config('shipstation.store_id');

        return filled($id) ? (string) $id : null;
    }

    public function assertStoreConfiguredForRealShipments(): void
    {
        if (! $this->isRealMode()) {
            return;
        }

        if (blank($this->storeId())) {
            throw new ShipStationApiException(
                'SHIPSTATION_STORE_ID is not configured. Real-mode V2 shipments require a store ID (FamTeesWorld.com / 533159 or the V2 se-… value your account accepts).',
            );
        }
    }

    public function assertWarehouseConfiguredForRealShipments(): void
    {
        if (! $this->isRealMode()) {
            return;
        }

        if (blank($this->warehouseIdForStore())) {
            throw new ShipStationApiException(
                'ShipStation warehouse is not configured for store '.($this->storeId() ?? '(none)').'. '
                .'Set shipstation.store_warehouse_map and/or SHIPSTATION_WAREHOUSE_ID. '
                .'Run php artisan shipstation:test-connection --show-warehouses to list available warehouses.',
            );
        }
    }

    private function resolveBaseUrl(?string $settingBaseUrl, string $defaultBaseUrl): string
    {
        $baseUrl = rtrim((string) ($settingBaseUrl ?: $defaultBaseUrl), '/');

        if (! $this->isRealMode()) {
            return $baseUrl;
        }

        $envBaseUrl = rtrim((string) config('shipstation.base_url', 'https://api.shipstation.com/v2'), '/');

        if (str_contains($baseUrl, 'ssapi.shipstation.com') || ! str_contains($baseUrl, 'api.shipstation.com')) {
            return $envBaseUrl;
        }

        return $baseUrl;
    }
}
