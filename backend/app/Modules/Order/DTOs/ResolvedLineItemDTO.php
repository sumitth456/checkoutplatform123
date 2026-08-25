<?php

namespace App\Modules\Order\DTOs;

use App\Models\Variant;
use App\Modules\Product\Enums\ProductType;

readonly class ResolvedLineItemDTO
{
    public function __construct(
        public Variant $variant,
        public int $quantity,
        public float $unitPrice,
        public float $lineTotal,
        public string $currency,
        public string $name,
        public string $sku,
    ) {}

    public function toOrderItemAttributes(): array
    {
        $product = $this->variant->product;
        $isSubscription = $product !== null && (
            $product->is_subscription
            || $product->type === ProductType::Subscription
        );

        return [
            'product_id' => $this->variant->product_id,
            'variant_id' => $this->variant->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'quantity' => $this->quantity,
            'unit_price' => $this->unitPrice,
            'total' => $this->lineTotal,
            'is_subscription' => $isSubscription,
        ];
    }
}
