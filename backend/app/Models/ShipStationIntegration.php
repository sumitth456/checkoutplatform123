<?php

namespace App\Models;

use App\Modules\ShipStation\Enums\ShipStationOrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipStationIntegration extends Model
{
    protected $table = 'shipstation_integrations';

    protected $fillable = [

        'order_id',

        'provider',

        'external_order_id',

        'external_shipment_id',

        'external_label_id',

        'tracking_number',

        'carrier',

        'carrier_id',

        'service',

        'service_code',

        'status',

        'label_status',

        'label_url',

        'shipment_cost',

        'synced_at',

        'last_api_endpoint',

        'last_api_method',

        'request_payload',

        'response_payload',

        'error_message',

        'remote_shipment_status',

        'remote_shipped_at',

        'last_auto_sync_at',

        'last_auto_sync_result',

        'auto_sync_meta',

        'tracking_sync_meta',

    ];

    protected function casts(): array
    {

        return [

            'status' => ShipStationOrderStatus::class,

            'request_payload' => 'array',

            'response_payload' => 'array',

            'shipment_cost' => 'decimal:2',

            'synced_at' => 'datetime',

            'remote_shipped_at' => 'datetime',

            'last_auto_sync_at' => 'datetime',

            'auto_sync_meta' => 'array',

            'tracking_sync_meta' => 'array',

        ];

    }

    public function order(): BelongsTo
    {

        return $this->belongsTo(Order::class);

    }
}
