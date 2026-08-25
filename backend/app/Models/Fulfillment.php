<?php

namespace App\Models;

use App\Modules\Order\Enums\DeliveryStatus;
use App\Modules\Order\Enums\FulfillmentRecordStatus;
use App\Modules\Order\Enums\ShipmentStatus;
use App\Policies\FulfillmentPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

#[UsePolicy(FulfillmentPolicy::class)]
class Fulfillment extends Model
{
    protected $fillable = [
        'uuid',
        'fulfillment_number',
        'order_id',
        'customer_id',
        'subscription_id',
        'fulfillment_house_id',
        'warehouse_id',
        'shipping_carrier_id',
        'shipping_rule_id',
        'fulfillment_batch_id',
        'parent_fulfillment_id',
        'delay_days',
        'scheduled_ship_date',
        'queued_at',
        'on_hold_at',
        'packed_at',
        'processing_at',
        'ready_to_ship_at',
        'shipped_at',
        'delivered_at',
        'returned_at',
        'cancelled_at',
        'failed_at',
        'estimated_delivery_at',
        'status',
        'warehouse_reference',
        'tracking_number',
        'tracking_url',
        'carrier',
        'shipping_method',
        'shipment_status',
        'delivery_status',
        'warehouse_request',
        'warehouse_response',
        'failure_reason',
        'rma_number',
        'notes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_ship_date' => 'date',
            'queued_at' => 'datetime',
            'on_hold_at' => 'datetime',
            'packed_at' => 'datetime',
            'processing_at' => 'datetime',
            'ready_to_ship_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'returned_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'failed_at' => 'datetime',
            'estimated_delivery_at' => 'datetime',
            'status' => FulfillmentRecordStatus::class,
            'shipment_status' => ShipmentStatus::class,
            'delivery_status' => DeliveryStatus::class,
            'warehouse_request' => 'array',
            'warehouse_response' => 'array',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Fulfillment $fulfillment): void {
            if (blank($fulfillment->uuid)) {
                $fulfillment->uuid = (string) Str::uuid();
            }
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function fulfillmentHouse(): BelongsTo
    {
        return $this->belongsTo(FulfillmentHouse::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function shippingCarrier(): BelongsTo
    {
        return $this->belongsTo(ShippingCarrier::class);
    }

    public function shippingRule(): BelongsTo
    {
        return $this->belongsTo(ShippingRule::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(FulfillmentBatch::class, 'fulfillment_batch_id');
    }

    public function parentFulfillment(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_fulfillment_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(FulfillmentItem::class);
    }

    public function timelineEvents(): HasMany
    {
        return $this->hasMany(FulfillmentTimelineEvent::class)->orderBy('occurred_at');
    }

    public function adminActivityLogs(): MorphMany
    {
        return $this->morphMany(AdminActivityLog::class, 'subject')->latest();
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function isDueForWarehouseSubmission(): bool
    {
        return in_array($this->status, [FulfillmentRecordStatus::Scheduled, FulfillmentRecordStatus::Pending, FulfillmentRecordStatus::ReadyToShip], true)
            && $this->scheduled_ship_date->lte(now()->startOfDay());
    }

    public function isOverdue(): bool
    {
        return in_array($this->status, [FulfillmentRecordStatus::Scheduled, FulfillmentRecordStatus::Pending], true)
            && $this->scheduled_ship_date->lt(now()->startOfDay());
    }

    public function daysUntilShip(): int
    {
        return (int) now()->startOfDay()->diffInDays(Carbon::parse($this->scheduled_ship_date), false);
    }
}
