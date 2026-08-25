<?php

namespace App\Modules\Payment\Repositories;

use App\Models\PaymentIdempotencyKey;
use App\Modules\Admin\Services\PlatformConfigResolver;
use App\Modules\Payment\Contracts\IdempotencyStoreInterface;
use App\Modules\Payment\DTOs\PaymentResponse;

class IdempotencyRepository implements IdempotencyStoreInterface
{
    public function __construct(
        private readonly PlatformConfigResolver $platformConfig,
    ) {}

    public function find(string $key, string $operation, string $requestHash): ?PaymentResponse
    {
        $record = PaymentIdempotencyKey::query()
            ->where('idempotency_key', $key)
            ->where('operation', $operation)
            ->where('request_hash', $requestHash)
            ->where('expires_at', '>', now())
            ->first();

        if ($record === null) {
            return null;
        }

        return PaymentResponse::fromArray($record->response_payload);
    }

    public function store(string $key, string $operation, string $requestHash, PaymentResponse $response): void
    {
        PaymentIdempotencyKey::query()->updateOrCreate(
            ['idempotency_key' => $key],
            [
                'operation' => $operation,
                'request_hash' => $requestHash,
                'response_payload' => $response->toArray(),
                'status' => $response->status->value,
                'expires_at' => now()->addMinutes($this->platformConfig->paymentIdempotencyTtlMinutes()),
            ],
        );
    }
}
