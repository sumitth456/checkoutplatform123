<?php

namespace App\Modules\ShipStation\Support;

use App\Catalog\Support\StandardCampaignOrderBumpBuilder;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Collection;

/**
 * Identifies face-shield order bumps (catalog SKU suffix and canonical product name).
 * Used only for ShipStation payload filtering — does not change CRM orders or totals.
 */
class ShipStationFaceShieldLineItemClassifier
{
    public function isFaceShieldLineItem(OrderItem $item): bool
    {
        $sku = strtoupper(trim((string) ($item->sku ?? '')));

        if ($sku !== '' && ($sku === 'FACE-SHIELDS' || str_ends_with($sku, '-FACE-SHIELDS'))) {
            return true;
        }

        $names = array_filter([
            (string) ($item->name ?? ''),
            (string) ($item->product?->name ?? ''),
        ]);

        foreach ($names as $name) {
            if ($this->nameIndicatesFaceShield($name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return Collection<int, OrderItem>
     */
    public function shippableLineItems(Order $order): Collection
    {
        $order->loadMissing(['items.product']);

        return $order->items->reject(fn (OrderItem $item): bool => $this->isFaceShieldLineItem($item))->values();
    }

    public function orderContainsFaceShieldLineItems(Order $order): bool
    {
        $order->loadMissing(['items.product']);

        return $order->items->contains(fn (OrderItem $item): bool => $this->isFaceShieldLineItem($item));
    }

    public function orderHasOnlyFaceShieldLineItems(Order $order): bool
    {
        $order->loadMissing(['items.product']);

        if ($order->items->isEmpty()) {
            return false;
        }

        return $order->items->every(fn (OrderItem $item): bool => $this->isFaceShieldLineItem($item));
    }

    public function orderHasShipStationShippableLineItems(Order $order): bool
    {
        return $this->shippableLineItems($order)->isNotEmpty();
    }

    private function nameIndicatesFaceShield(string $name): bool
    {
        $normalized = trim($name);

        if ($normalized === '') {
            return false;
        }

        if (strcasecmp($normalized, StandardCampaignOrderBumpBuilder::FACE_SHIELDS_NAME) === 0) {
            return true;
        }

        return (bool) preg_match('/\bface\s*shields?\b/i', $normalized);
    }
}
