<?php

namespace App\Modules\ShipStation\Exceptions;

class ShipStationAutomaticProcessingHoldException extends ShipStationApiException
{
    public function __construct(string $orderNumber)
    {
        parent::__construct(
            "Order {$orderNumber} is on ShipStation automatic processing hold. "
            .'Automatic and manual ShipStation send, shipment, and label actions are blocked until the hold is removed.',
        );
    }
}
