<?php

namespace App\Models;

use App\Modules\Order\Enums\WarehouseConnectionStatus;
use App\Modules\ShipStation\Enums\ShipStationMode;
use App\Policies\ShipStationSettingPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[UsePolicy(ShipStationSettingPolicy::class)]
class ShipStationSetting extends Model
{
    protected $table = 'shipstation_settings';

    protected $fillable = [
        'name',
        'base_url',
        'api_key',
        'api_secret',
        'timeout_seconds',
        'is_active',
        'connection_status',
        'last_tested_at',
        'last_test_error',
    ];

    protected $hidden = [
        'api_key',
        'api_secret',
    ];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'api_secret' => 'encrypted',
            'is_active' => 'boolean',
            'connection_status' => WarehouseConnectionStatus::class,
            'last_tested_at' => 'datetime',
        ];
    }

    public function adminActivityLogs(): MorphMany
    {
        return $this->morphMany(AdminActivityLog::class, 'subject')->latest();
    }

    public function isConfigured(): bool
    {
        $mode = ShipStationMode::fromConfig();

        if ($mode->isMock()) {
            return true;
        }

        return filled($this->api_key) || filled(config('shipstation.api_key'));
    }

    public static function current(): self
    {
        $config = config('shipstation');
        $mockMode = ShipStationMode::fromConfig()->isMock();

        return static::query()->firstOrCreate(
            ['name' => 'ShipStation'],
            [
                'base_url' => (string) ($config['base_url'] ?? 'https://api.shipstation.com/v2'),
                'api_key' => (string) ($config['api_key'] ?? ''),
                'api_secret' => (string) ($config['api_secret'] ?? ''),
                'timeout_seconds' => (int) ($config['timeout_seconds'] ?? 30),
                'is_active' => $mockMode,
                'connection_status' => WarehouseConnectionStatus::Unknown,
            ],
        );
    }
}
