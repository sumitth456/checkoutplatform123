<?php

namespace App\Modules\ShipStation\Support;

use App\Modules\ShipStation\Exceptions\ShipStationApiException;

/**
 * Resolves ShipStation V2 warehouse_id from configured store_id using store_warehouse_map.
 */
final class ShipStationStoreWarehouseResolver
{
    /**
     * @return list<string> Lookup keys for a configured store id (numeric legacy and se- prefixed).
     */
    public function storeLookupKeys(?string $storeId): array
    {
        if (blank($storeId)) {
            return [];
        }

        $storeId = trim((string) $storeId);
        $keys = [$storeId];

        if (preg_match('/^se-(\d+)$/i', $storeId, $matches)) {
            $keys[] = $matches[1];
        } elseif (ctype_digit($storeId)) {
            $keys[] = 'se-'.$storeId;
        }

        return array_values(array_unique($keys));
    }

    /**
     * Warehouse for a ShipStation store. Does not mutate shipments or read orders.
     *
     * @throws ShipStationApiException when mapping is required but missing
     */
    public function resolveWarehouseId(
        ?string $storeId,
        ?string $globalWarehouseId,
        bool $allowGlobalFallback,
    ): ?string {
        $map = config('shipstation.store_warehouse_map', []);

        if (! is_array($map)) {
            $map = [];
        }

        foreach ($this->storeLookupKeys($storeId) as $key) {
            if (filled($map[$key] ?? null)) {
                return (string) $map[$key];
            }
        }

        if (blank($storeId)) {
            return filled($globalWarehouseId) ? (string) $globalWarehouseId : null;
        }

        if ($allowGlobalFallback && filled($globalWarehouseId)) {
            return (string) $globalWarehouseId;
        }

        throw new ShipStationApiException(sprintf(
            'No ShipStation warehouse mapping for store_id [%s]. Configure shipstation.store_warehouse_map or enable SHIPSTATION_STORE_WAREHOUSE_FALLBACK.',
            $storeId,
        ));
    }
}
