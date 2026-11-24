<?php

namespace App\Modules\ShipStation\Support;

final class ShipStationTrackingSyncGate
{
    public function isEnabled(): bool
    {
        return (bool) config('shipstation.tracking_sync.enabled', false);
    }

    public function isShadowMode(): bool
    {
        return (bool) config('shipstation.tracking_sync.shadow_mode', true);
    }

    public function mayUpdateFulfillment(): bool
    {
        return (bool) config('shipstation.tracking_sync.fulfillment_updates', false);
    }

    public function mayCallMarkDelivered(): bool
    {
        return $this->mayUpdateFulfillment();
    }
}