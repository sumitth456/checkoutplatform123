<?php

namespace App\Modules\ShipStation\Services;

/**
 * Global kill-switch for automatic ShipStation label purchase, warehouse submission,
 * and dispatch. Does not block paid-order pending shipment import or manual admin actions.
 */
class ShipStationAutomaticFulfillmentGateService
{
    public const SKIP_REASON = 'automatic_fulfillment_disabled';

    public function isAutomaticFulfillmentEnabled(): bool
    {
        return filter_var(config('shipstation.auto_fulfillment_enabled', false), FILTER_VALIDATE_BOOLEAN);
    }

    public function blocksAutomaticFulfillment(): bool
    {
        return ! $this->isAutomaticFulfillmentEnabled();
    }
}
