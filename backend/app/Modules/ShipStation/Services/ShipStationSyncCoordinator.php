<?php

namespace App\Modules\ShipStation\Services;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;

final class ShipStationSyncCoordinator
{
    public function lockForOrder(int $orderId): Lock
    {
        $seconds = (int) config('shipstation.tracking_sync.lock_seconds', 120);

        return Cache::lock('shipstation-sync:order:'.$orderId, $seconds);
    }

    public function lockForFulfillmentIntegration(int $integrationId): Lock
    {
        $seconds = (int) config('shipstation.tracking_sync.lock_seconds', 120);

        return Cache::lock('shipstation-sync:reshipment:'.$integrationId, $seconds);
    }
}