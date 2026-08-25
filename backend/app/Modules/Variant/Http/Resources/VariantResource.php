<?php

namespace App\Modules\Variant\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'product_id' => $this->product_id,
            'name' => $this->name,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'currency' => $this->currency,
            'price' => $this->price,
            'compare_at_price' => $this->compare_at_price,
            'cost' => $this->when($this->cost !== null, $this->cost),
            'inventory_quantity' => $this->inventory_quantity,
            'inventory_policy' => $this->inventory_policy?->value,
            'track_inventory' => $this->track_inventory,
            'weight' => $this->weight,
            'weight_unit' => $this->weight_unit,
            'options' => $this->options,
            'images' => $this->images,
            'is_default' => $this->is_default,
            'status' => $this->status?->value,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->whenNotNull($this->deleted_at),
        ];
    }
}
