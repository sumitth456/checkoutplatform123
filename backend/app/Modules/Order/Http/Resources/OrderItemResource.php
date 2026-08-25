<?php

namespace App\Modules\Order\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'sku' => $this->sku,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'discount_amount' => $this->discount_amount,
            'tax_amount' => $this->tax_amount,
            'total' => $this->total,
            'is_subscription' => $this->is_subscription,
            'product_uuid' => $this->whenLoaded('product', fn () => $this->product?->uuid),
            'variant_uuid' => $this->whenLoaded('variant', fn () => $this->variant?->uuid),
        ];
    }
}
