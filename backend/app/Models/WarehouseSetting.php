<?php



namespace App\Models;



use App\Modules\Order\Enums\WarehouseConnectionStatus;

use App\Policies\WarehouseSettingPolicy;

use Illuminate\Database\Eloquent\Attributes\UsePolicy;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\MorphMany;



#[UsePolicy(WarehouseSettingPolicy::class)]

class WarehouseSetting extends Model

{

    protected $fillable = [

        'name',

        'base_url',

        'api_key',

        'timeout_seconds',

        'create_shipment_path',

        'is_active',

        'connection_status',

        'last_tested_at',

        'last_test_error',

    ];



    protected function casts(): array

    {

        return [

            'api_key' => 'encrypted',

            'is_active' => 'boolean',

            'connection_status' => WarehouseConnectionStatus::class,

            'last_tested_at' => 'datetime',

        ];

    }



    public function adminActivityLogs(): MorphMany

    {

        return $this->morphMany(AdminActivityLog::class, 'subject')->latest();

    }



    public static function current(): self

    {

        $config = config('fulfillment.warehouse');



        return static::query()->firstOrCreate(

            ['name' => 'Primary warehouse'],

            [

                'base_url' => (string) ($config['base_url'] ?? 'https://warehouse.example.com/api'),

                'api_key' => (string) ($config['api_key'] ?? ''),

                'timeout_seconds' => (int) ($config['timeout_seconds'] ?? 30),

                'create_shipment_path' => (string) ($config['create_shipment_path'] ?? '/v1/shipments'),

                'is_active' => true,

                'connection_status' => WarehouseConnectionStatus::Unknown,

            ],

        );

    }

}

