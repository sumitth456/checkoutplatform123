<?php

namespace App\Models;

use App\Modules\Payment\Enums\LedgerEntryType;
use App\Modules\Payment\Enums\PaymentProvider;
use App\Modules\Payment\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PaymentLedgerEntry extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'uuid',
        'transaction_id',
        'provider',
        'entry_type',
        'status_from',
        'status_to',
        'amount',
        'currency',
        'external_id',
        'idempotency_key',
        'payload',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'provider' => PaymentProvider::class,
            'entry_type' => LedgerEntryType::class,
            'status_from' => PaymentStatus::class,
            'status_to' => PaymentStatus::class,
            'amount' => 'decimal:2',
            'payload' => 'array',
            'recorded_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PaymentLedgerEntry $entry): void {
            if (blank($entry->uuid)) {
                $entry->uuid = (string) Str::uuid();
            }

            $entry->recorded_at ??= now();
        });
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
