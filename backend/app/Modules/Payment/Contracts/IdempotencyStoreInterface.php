<?php

namespace App\Modules\Payment\Contracts;

use App\Modules\Payment\DTOs\PaymentResponse;

interface IdempotencyStoreInterface
{
    public function find(string $key, string $operation, string $requestHash): ?PaymentResponse;

    public function store(string $key, string $operation, string $requestHash, PaymentResponse $response): void;
}
