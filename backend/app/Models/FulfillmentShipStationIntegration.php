<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FulfillmentShipStationIntegration extends Model
{
    protected $table = 'fulfillment_shipstation_integrations';

    protected $fillable = [
        'fulfillment_id',
        'order_id',
        'reship_request_id',
        'provider',
        'external_reference',
        'external_shipment_id',
        'external_label_id',
        'tracking_number',
        'carrier',
        'service_code',
        'remote_shipment_status',
        'remote_shipped_at',
        'last_synced_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'remote_shipped_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function fulfillment(): BelongsTo
    {
        return $this->belongsTo(Fulfillment::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function reshipRequest(): BelongsTo
    {
        return $this->belongsTo(ReshipRequest::class);
    }
}