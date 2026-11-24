<?php

namespace App\Modules\ShipStation\Support;

use App\Models\Order;

final class ShipStationPilotAutoStatusSyncGate
{
    public function isFeatureEnabled(): bool
    {
        return (bool) config('shipstation.pilot_auto_status_sync.enabled', false);
    }

    public function isPilotOrder(Order $order): bool
    {
        $uuids = config('shipstation.pilot_auto_status_sync.order_uuids', []);

        if ($uuids === []) {
            return false;
        }

        return in_array((string) $order->uuid, array_map('strval', $uuids), true);
    }

    public function allowsAutomaticSync(Order $order): bool
    {
        return $this->isFeatureEnabled() && $this->isPilotOrder($order);
    }
}