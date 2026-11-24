<?php

namespace App\Modules\ShipStation\Services;

use App\Models\Order;
use App\Modules\ShipStation\Exceptions\ShipStationOrderSyncExclusionException;

/**
 * Persistent per-order ShipStation sync exclusion (UUID allowlist in config).
 * Blocks outbound sync/submission only; does not cancel remote shipments.
 */
class ShipStationOrderSyncExclusionService
{
    /** ORD-20261118-942D33 — already has remote shipment se-1056100016 (do not mutate). */
    public const ORD_20261118_942D33_UUID = '04191080-7815-4e24-8f99-800d64f69d2f';

    public function isExcluded(Order $order): bool
    {
        $uuid = strtolower(trim((string) ($order->uuid ?? '')));

        if ($uuid === '') {
            return false;
        }

        /** @var list<string> $excluded */
        $excluded = config('shipstation.sync_excluded_order_uuids', []);

        foreach ($excluded as $configured) {
            if ($uuid === strtolower(trim((string) $configured))) {
                return true;
            }
        }

        return false;
    }

    public function assertSynchronizationAllowed(Order $order): void
    {
        if ($this->isExcluded($order)) {
            throw new ShipStationOrderSyncExclusionException;
        }
    }
}