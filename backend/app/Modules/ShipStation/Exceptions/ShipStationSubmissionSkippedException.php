<?php

namespace App\Modules\ShipStation\Exceptions;

use App\Modules\ShipStation\Support\ShipStationSubmissionDecision;
use RuntimeException;

class ShipStationSubmissionSkippedException extends RuntimeException
{
    public function __construct(
        public readonly ShipStationSubmissionDecision $decision,
        ?string $message = null,
        string $action = 'shipment',
    ) {
        parent::__construct($message ?? $decision->skipMessage($action));
    }
}
