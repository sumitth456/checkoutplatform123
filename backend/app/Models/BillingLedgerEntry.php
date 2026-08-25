<?php

namespace App\Models;

use App\Modules\Subscription\Enums\BillingLedgerOutcome;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class BillingLedgerEntry extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'uuid',
        'subscription_id',
        'cycle_step',
        'retry_attempt',
        'outcome',
        'amount',
        'currency',
        'idempotency_key',
        'transaction_id',
        'failure_code',
        'failure_message',
        'payload',
        'attempted_at',
    ];

    protected function casts(): array
    {
        return [
            'outcome' => BillingLedgerOutcome::class,
            'amount' => 'decimal:2',
            'payload' => 'array',
            'attempted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (BillingLedgerEntry $entry): void {
            if (blank($entry->uuid)) {
                $entry->uuid = (string) Str::uuid();
            }

            $entry->attempted_at ??= now();
        });
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
