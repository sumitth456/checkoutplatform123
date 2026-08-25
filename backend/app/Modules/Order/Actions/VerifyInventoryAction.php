<?php

namespace App\Modules\Order\Actions;

use App\Modules\Order\DTOs\ResolvedLineItemDTO;
use Illuminate\Validation\ValidationException;

class VerifyInventoryAction
{
    public function execute(ResolvedLineItemDTO $lineItem, int $index): void
    {
        if (! $lineItem->variant->track_inventory) {
            return;
        }

        $available = (int) $lineItem->variant->inventory_quantity;

        if ($available < $lineItem->quantity) {
            throw ValidationException::withMessages([
                "items.{$index}.quantity" => ["Insufficient inventory for SKU {$lineItem->sku}."],
            ]);
        }
    }
}
