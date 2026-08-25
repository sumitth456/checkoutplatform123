<?php

namespace App\Models;

use App\Policies\CheckoutPaymentFailurePolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[UsePolicy(CheckoutPaymentFailurePolicy::class)]
class CheckoutPaymentFailure extends Model
{
    protected $fillable = [
        'uuid',
        'idempotency_key',
        'customer_email',
        'amount',
        'currency',
        'provider',
        'failure_code',
        'failure_message',
        'payment_intent_id',
        'request_payload',
        'gateway_response',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'request_payload' => 'array',
            'gateway_response' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CheckoutPaymentFailure $failure): void {
            if (blank($failure->uuid)) {
                $failure->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
