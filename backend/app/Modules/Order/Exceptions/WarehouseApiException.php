<?php

namespace App\Modules\Order\Exceptions;

use RuntimeException;

class WarehouseApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly array $context = [],
    ) {
        parent::__construct($message);
    }

    public static function transportFailure(string $message, array $context = []): self
    {
        return new self($message, $context);
    }
}
