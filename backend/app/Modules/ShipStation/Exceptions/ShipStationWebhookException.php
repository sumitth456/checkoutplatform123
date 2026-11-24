<?php

namespace App\Modules\ShipStation\Exceptions;

use RuntimeException;

class ShipStationWebhookException extends RuntimeException
{
    private function __construct(
        string $message,
        private readonly int $httpStatus,
    ) {
        parent::__construct($message);
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }

    public static function unauthorized(): self
    {
        return new self('ShipStation webhook unauthorized.', 401);
    }

    public static function notConfigured(): self
    {
        return new self('ShipStation webhook secret is not configured.', 503);
    }
}
