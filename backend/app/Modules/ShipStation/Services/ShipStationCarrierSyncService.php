<?php

namespace App\Modules\ShipStation\Services;

use App\Models\ShippingCarrier;
use App\Models\ShipStationIntegrationLog;
use App\Modules\ShipStation\Contracts\ShipStationServiceInterface;
use Illuminate\Support\Str;

class ShipStationCarrierSyncService
{
    public function __construct(
        private readonly ShipStationConfigResolver $config,
        private readonly ShipStationServiceInterface $api,
    ) {}

    /**
     * @return array{created: int, updated: int}
     */
    public function syncCarriers(): array
    {
        if (! $this->config->isEnabled()) {
            return ['created' => 0, 'updated' => 0];
        }

        $created = 0;
        $updated = 0;

        foreach ($this->api->getCarriers() as $carrier) {
            $code = strtolower((string) ($carrier['code'] ?? ''));

            if ($code === '') {
                continue;
            }

            $services = $this->api->getServices($code);
            $serviceNames = collect($services)
                ->map(fn (array $service): string => (string) ($service['name'] ?? $service['code'] ?? ''))
                ->filter()
                ->values()
                ->all();

            $existing = ShippingCarrier::query()->where('code', $code)->first();

            $payload = [
                'name' => (string) ($carrier['name'] ?? strtoupper($code)),
                'code' => $code,
                'provider' => 'shipstation',
                'services' => $serviceNames,
                'is_active' => (bool) ($carrier['requiresFundedAccount'] ?? true) === false ? true : true,
            ];

            if ($existing) {
                $existing->update([
                    'name' => $payload['name'],
                    'provider' => 'shipstation',
                    'services' => $serviceNames,
                ]);
                $updated++;
            } else {
                ShippingCarrier::query()->create(array_merge($payload, [
                    'uuid' => (string) Str::uuid(),
                ]));
                $created++;
            }
        }

        ShipStationIntegrationLog::query()->create([
            'request_type' => 'sync_carriers',
            'success' => true,
            'context' => ['created' => $created, 'updated' => $updated],
        ]);

        return compact('created', 'updated');
    }
}
