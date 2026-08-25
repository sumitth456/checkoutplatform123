<?php

namespace App\Modules\Ach\Services;

use App\Models\AchPaymentLog;
use App\Models\AchProvider;
use App\Modules\Ach\Support\AchDataMasker;

final class AchPaymentLogger
{
    /**
     * @param  array<string, mixed>  $request
     * @param  array<string, mixed>  $response
     */
    public function log(
        string $action,
        string $status,
        array $request = [],
        array $response = [],
        ?AchProvider $provider = null,
        ?int $customerId = null,
        ?int $orderId = null,
        ?string $idempotencyKey = null,
    ): AchPaymentLog {
        return AchPaymentLog::query()->create([
            'ach_provider_id' => $provider?->id,
            'customer_id' => $customerId,
            'order_id' => $orderId,
            'action' => $action,
            'status' => $status,
            'request_payload' => AchDataMasker::mask($request),
            'response_payload' => AchDataMasker::mask($response),
            'idempotency_key' => $idempotencyKey,
        ]);
    }
}
