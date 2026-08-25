<?php

namespace App\Modules\Payment\DTOs;

use App\Modules\Payment\Enums\PaymentProvider;

readonly class RefundRequest
{
    public function __construct(
        public string $transactionId,
        public string $amount,
        public string $currency,
        public ?string $reason = null,
        public ?PaymentProvider $provider = null,
        public ?string $idempotencyKey = null,
        public array $metadata = [],
    ) {}
}
