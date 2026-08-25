<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AchPaymentLog extends Model
{
    protected $fillable = [
        'ach_provider_id',
        'customer_id',
        'order_id',
        'action',
        'status',
        'request_payload',
        'response_payload',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'response_payload' => 'array',
        ];
    }

    public function achProvider(): BelongsTo
    {
        return $this->belongsTo(AchProvider::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
