<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipStationIntegrationLog extends Model
{
    protected $table = 'shipstation_integration_logs';

    protected $fillable = [
        'request_type',
        'order_id',
        'fulfillment_id',
        'shipstation_order_id',
        'shipstation_shipment_id',
        'success',
        'http_status',
        'error_message',
        'context',
        'request_payload',
        'response_payload',
    ];

    protected function casts(): array
    {
        return [
            'success' => 'boolean',
            'context' => 'array',
            'request_payload' => 'array',
            'response_payload' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function fulfillment(): BelongsTo
    {
        return $this->belongsTo(Fulfillment::class);
    }
}
