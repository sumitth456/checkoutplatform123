<?php

namespace App\Modules\ShipStation\Exceptions;

use RuntimeException;

class ShipStationApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $httpStatus = null,
        public readonly ?array $response = null,
    ) {
        parent::__construct($message);
    }
}
