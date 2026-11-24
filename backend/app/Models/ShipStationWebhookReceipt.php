<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ShipStationWebhookReceipt extends Model
{
    protected $table = 'shipstation_webhook_receipts';

    public const STATUS_RECEIVED = 'received';

    public const STATUS_DISPATCHED = 'dispatched';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_PROCESSED = 'processed';

    public const STATUS_IGNORED = 'ignored';

    public const STATUS_FAILED = 'failed';

    public const SCOPE_ORDER = 'order';

    public const SCOPE_GLOBAL_TRACKING = 'global_tracking';

    protected $fillable = [
        'uuid',
        'delivery_fingerprint',
        'resource_type',
        'resource_url',
        'scope_type',
        'scope_id',
        'order_id',
        'fulfillment_id',
        'status',
        'outbox_status',
        'dispatch_attempts',
        'dispatched_at',
        'processing_started_at',
        'processed_at',
        'ignore_reason',
        'failure_reason',
        'payload_meta',
    ];

    protected function casts(): array
    {
        return [
            'dispatched_at' => 'datetime',
            'processing_started_at' => 'datetime',
            'processed_at' => 'datetime',
            'payload_meta' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $receipt): void {
            if (blank($receipt->uuid)) {
                $receipt->uuid = (string) Str::uuid();
            }
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function fulfillment(): BelongsTo
    {
        return $this->belongsTo(Fulfillment::class);
    }
}