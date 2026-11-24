<?php

namespace App\Modules\ShipStation\Exceptions;

class ShipStationOrderSyncExclusionException extends ShipStationApiException
{
    public const MESSAGE = 'This order is excluded from ShipStation synchronization.';

    public function __construct()
    {
        parent::__construct(self::MESSAGE);
    }
}