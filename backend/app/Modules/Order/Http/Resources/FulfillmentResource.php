<?php

namespace App\Modules\Order\Http\Resources;

use App\Models\Fulfillment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Fulfillment */
class FulfillmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'delay_days' => $this->delay_days,
            'scheduled_ship_date' => $this->scheduled_ship_date?->toDateString(),
            'shipped_at' => $this->shipped_at?->toIso8601String(),
            'status' => $this->status->value,
            'warehouse_reference' => $this->warehouse_reference,
            'tracking_number' => $this->tracking_number,
            'carrier' => $this->carrier,
            'shipment_status' => $this->shipment_status->value,
            'delivery_status' => $this->delivery_status->value,
        ];
    }
}
