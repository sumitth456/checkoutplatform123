<?php

namespace App\Modules\ShipStation\Services;

use App\Models\ShipStationSetting;
use App\Modules\Order\Enums\WarehouseConnectionStatus;
use App\Modules\ShipStation\Enums\ShipStationMode;
use App\Modules\ShipStation\Exceptions\ShipStationApiException;

class ShipStationConnectionService
{
    public function __construct(
        private readonly ShipStationConfigResolver $config,
        private readonly ShipStationV2ApiClient $v2Api,
    ) {}

    /**
     * @return array{
     *     success: bool,
     *     connected: bool,
     *     authentication: string,
     *     message: string,
     *     mode: string,
     *     endpoint: string|null,
     *     http_status: int|null,
     *     carriers: array{success: bool, count: int|null, http_status: int|null, message: string|null},
     *     warehouses: array{success: bool, count: int|null, http_status: int|null, message: string|null}
     * }
     */
    public function test(?ShipStationSetting $setting = null, bool $envOnly = false): array
    {
        if (! $envOnly && $setting === null) {
            try {
                $setting = ShipStationSetting::current();
            } catch (\Throwable) {
                $envOnly = true;
            }
        }

        if ($this->config->isMockMode()) {
            return $this->testMock($setting, $envOnly);
        }

        $apiKey = (string) $this->config->resolve($setting, $envOnly)['api_key'];

        if (! filled($apiKey)) {
            $this->persistConnectionFailure($setting, $envOnly, 'ShipStation API key is required.');

            return $this->failure('ShipStation API key is required.', $setting);
        }

        return $this->testReal($setting, $envOnly);
    }

    /**
     * @return array{
     *     success: bool,
     *     connected: bool,
     *     authentication: string,
     *     message: string,
     *     mode: string,
     *     endpoint: string|null,
     *     http_status: int|null,
     *     carriers: array{success: bool, count: int|null, http_status: int|null, message: string|null},
     *     warehouses: array{success: bool, count: int|null, http_status: int|null, message: string|null}
     * }
     */
    private function testMock(?ShipStationSetting $setting, bool $envOnly = false): array
    {
        $endpoint = $this->v2Api->baseUrl($envOnly).'/carriers';

        try {
            $this->v2Api->getCarriers($envOnly);

            $this->persistConnectionSuccess($setting, $envOnly);

            return [
                'success' => true,
                'connected' => true,
                'authentication' => 'success',
                'message' => 'ShipStation mock API connection successful',
                'mode' => ShipStationMode::Mock->value,
                'endpoint' => $endpoint,
                'http_status' => 200,
                'carriers' => [
                    'success' => true,
                    'count' => null,
                    'http_status' => 200,
                    'message' => null,
                ],
                'warehouses' => [
                    'success' => true,
                    'count' => null,
                    'http_status' => null,
                    'message' => 'Not tested in mock mode',
                ],
            ];
        } catch (ShipStationApiException|\Throwable $exception) {
            $this->persistConnectionFailure($setting, $envOnly, $exception->getMessage());

            return [
                'success' => false,
                'connected' => false,
                'authentication' => 'failed',
                'message' => $exception->getMessage(),
                'mode' => ShipStationMode::Mock->value,
                'endpoint' => $endpoint,
                'http_status' => $exception instanceof ShipStationApiException ? $exception->httpStatus : 500,
                'carriers' => [
                    'success' => false,
                    'count' => null,
                    'http_status' => $exception instanceof ShipStationApiException ? $exception->httpStatus : 500,
                    'message' => $exception->getMessage(),
                ],
                'warehouses' => [
                    'success' => false,
                    'count' => null,
                    'http_status' => null,
                    'message' => 'Not tested',
                ],
            ];
        }
    }

    /**
     * @return array{
     *     success: bool,
     *     connected: bool,
     *     authentication: string,
     *     message: string,
     *     mode: string,
     *     endpoint: string|null,
     *     http_status: int|null,
     *     carriers: array{success: bool, count: int|null, http_status: int|null, message: string|null},
     *     warehouses: array{success: bool, count: int|null, http_status: int|null, message: string|null}
     * }
     */
    private function testReal(?ShipStationSetting $setting, bool $envOnly = false): array
    {
        $endpoint = $this->v2Api->baseUrl($envOnly).'/carriers';
        $warehousesResult = [
            'success' => false,
            'count' => null,
            'http_status' => null,
            'message' => null,
        ];

        try {
            $carriersResponse = $this->v2Api->getCarriers($envOnly);
            $carrierCount = count(is_array($carriersResponse['carriers'] ?? null) ? $carriersResponse['carriers'] : []);

            $warehousesResult = $this->testWarehouses($envOnly);

            $this->persistConnectionSuccess($setting, $envOnly);

            $warehouseNote = $warehousesResult['success']
                ? "{$warehousesResult['count']} warehouses"
                : 'warehouses check failed';

            return [
                'success' => true,
                'connected' => true,
                'authentication' => 'success',
                'message' => "ShipStation V2 connection successful ({$carrierCount} carriers; {$warehouseNote})",
                'mode' => ShipStationMode::Real->value,
                'endpoint' => $endpoint,
                'http_status' => 200,
                'carriers' => [
                    'success' => true,
                    'count' => $carrierCount,
                    'http_status' => 200,
                    'message' => null,
                ],
                'warehouses' => $warehousesResult,
            ];
        } catch (ShipStationApiException $exception) {
            $this->persistConnectionFailure($setting, $envOnly, $exception->getMessage());

            return [
                'success' => false,
                'connected' => false,
                'authentication' => 'failed',
                'message' => $exception->getMessage(),
                'mode' => ShipStationMode::Real->value,
                'endpoint' => $endpoint,
                'http_status' => $exception->httpStatus,
                'carriers' => [
                    'success' => false,
                    'count' => null,
                    'http_status' => $exception->httpStatus,
                    'message' => $exception->getMessage(),
                ],
                'warehouses' => $warehousesResult,
            ];
        } catch (\Throwable $exception) {
            $this->persistConnectionFailure($setting, $envOnly, $exception->getMessage());

            return array_merge($this->failure($exception->getMessage(), $setting, ShipStationMode::Real->value, $endpoint), [
                'carriers' => [
                    'success' => false,
                    'count' => null,
                    'http_status' => null,
                    'message' => $exception->getMessage(),
                ],
                'warehouses' => $warehousesResult,
            ]);
        }
    }

    /**
     * @return array{success: bool, count: int|null, http_status: int|null, message: string|null}
     */
    private function testWarehouses(bool $envOnly): array
    {
        try {
            $response = $this->v2Api->listWarehouses($envOnly);
            $count = count(is_array($response['warehouses'] ?? null) ? $response['warehouses'] : []);

            return [
                'success' => true,
                'count' => $count,
                'http_status' => 200,
                'message' => null,
            ];
        } catch (ShipStationApiException $exception) {
            return [
                'success' => false,
                'count' => null,
                'http_status' => $exception->httpStatus,
                'message' => $exception->getMessage(),
            ];
        } catch (\Throwable $exception) {
            return [
                'success' => false,
                'count' => null,
                'http_status' => null,
                'message' => $exception->getMessage(),
            ];
        }
    }

    private function persistConnectionSuccess(?ShipStationSetting $setting, bool $envOnly): void
    {
        if ($envOnly || $setting === null) {
            return;
        }

        $setting->update([
            'connection_status' => WarehouseConnectionStatus::Healthy,
            'last_tested_at' => now(),
            'last_test_error' => null,
        ]);
    }

    private function persistConnectionFailure(?ShipStationSetting $setting, bool $envOnly, string $message): void
    {
        if ($envOnly || $setting === null) {
            return;
        }

        $setting->update([
            'connection_status' => WarehouseConnectionStatus::Unhealthy,
            'last_tested_at' => now(),
            'last_test_error' => $message,
        ]);
    }

    /**
     * @return array{
     *     success: bool,
     *     connected: bool,
     *     authentication: string,
     *     message: string,
     *     mode: string,
     *     endpoint: string|null,
     *     http_status: int|null,
     *     carriers: array{success: bool, count: int|null, http_status: int|null, message: string|null},
     *     warehouses: array{success: bool, count: int|null, http_status: int|null, message: string|null}
     * }
     */
    private function failure(
        string $message,
        ?ShipStationSetting $setting,
        string $mode = 'real',
        ?string $endpoint = null,
    ): array {
        return [
            'success' => false,
            'connected' => false,
            'authentication' => 'failed',
            'message' => $message,
            'mode' => $mode,
            'endpoint' => $endpoint,
            'http_status' => null,
            'carriers' => [
                'success' => false,
                'count' => null,
                'http_status' => null,
                'message' => $message,
            ],
            'warehouses' => [
                'success' => false,
                'count' => null,
                'http_status' => null,
                'message' => 'Not tested',
            ],
        ];
    }
}
