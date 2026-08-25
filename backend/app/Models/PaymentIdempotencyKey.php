<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentIdempotencyKey extends Model
{
    protected $fillable = [
        'idempotency_key',
        'operation',
        'request_hash',
        'response_payload',
        'status',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'response_payload' => 'array',
            'expires_at' => 'datetime',
        ];
    }
}
