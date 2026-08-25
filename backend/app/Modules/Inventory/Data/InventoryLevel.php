<?php

namespace App\Modules\Inventory\Data;

use App\Models\Variant;
use App\Modules\Variant\Enums\InventoryPolicy;

readonly class InventoryLevel
{
    public function __construct(
        public int $onHand,
        public int $reserved,
        public int $available,
        public bool $trackInventory,
        public InventoryPolicy $inventoryPolicy,
        public string $variantUuid,
        public string $sku,
    ) {}

    public static function fromVariant(Variant $variant, int $reserved): self
    {
        $onHand = $variant->inventory_quantity;

        return new self(
            onHand: $onHand,
            reserved: $reserved,
            available: max(0, $onHand - $reserved),
            trackInventory: $variant->track_inventory,
            inventoryPolicy: $variant->inventory_policy,
            variantUuid: $variant->uuid,
            sku: $variant->sku,
        );
    }

    public function toArray(): array
    {
        return [
            'variant_uuid' => $this->variantUuid,
            'sku' => $this->sku,
            'on_hand' => $this->onHand,
            'reserved' => $this->reserved,
            'available' => $this->available,
            'track_inventory' => $this->trackInventory,
            'inventory_policy' => $this->inventoryPolicy->value,
        ];
    }
}
