<?php

namespace App\Modules\Payment\Exceptions;

use App\Modules\Payment\Enums\PaymentProvider;
use RuntimeException;

class PaymentGatewayException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly PaymentProvider $provider,
        public readonly array $context = [],
    ) {
        parent::__construct($message);
    }

    public static function transportFailure(PaymentProvider $provider, string $message, array $context = []): self
    {
        return new self($message, $provider, $context);
    }

    public static function circuitOpen(PaymentProvider $provider): self
    {
        return new self('Payment gateway circuit is open.', $provider, ['circuit' => 'open']);
    }
}
