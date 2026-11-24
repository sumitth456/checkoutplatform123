<?php

namespace App\Models;

use App\Modules\Order\Enums\OrderFulfillmentStatus;
use App\Modules\Order\Enums\OrderFraudStatus;
use App\Modules\Order\Enums\OrderPaymentStatus;
use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Order\Enums\OrderType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'order_number',
        'order_type',
        'parent_order_id',
        'source_subscription_id',
        'source_billing_ledger_entry_id',
        'billing_cycle_step',
        'rebill_number',
        'rebill_retry_count',
        'customer_id',
        'funnel_id',
        'platform_campaign_id',
        'affiliate_id',
        'affiliate_click_id',
        'affiliate_offer_id',
        'affiliate_sub_ids',
        'funnel_step_id',
        'payment_gateway_id',
        'payment_method_type',
        'coupon_id',
        'status',
        'payment_status',
        'fraud_status',
        'fraud_score',
        'duplicate_of_order_id',
        'fulfillment_status',
        'currency',
        'subtotal',
        'discount_total',
        'tax_total',
        'shipping_total',
        'grand_total',
        'refunded_total',
        'coupon_code',
        'billing_address',
        'shipping_address',
        'customer_email',
        'customer_phone',
        'notes',
        'internal_notes',
        'ip_address',
        'user_agent',
        'device_type',
        'campaign_id',
        'country_code',
        'landing_page_url',
        'landing_page_id',
        'referrer_url',
        'utm_parameters',
        'landing_page_data',
        'placed_at',
        'paid_at',
        'fulfilled_at',
        'cancelled_at',
        'cancellation_reason',
        'metadata',
        'shipstation_order_id',
        'shipstation_status',
        'shipstation_synced_at',
        'shipstation_sync_error',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'order_type' => OrderType::class,
            'payment_status' => OrderPaymentStatus::class,
            'fraud_status' => OrderFraudStatus::class,
            'fraud_score' => 'decimal:2',
            'fulfillment_status' => OrderFulfillmentStatus::class,
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'shipping_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'refunded_total' => 'decimal:2',
            'billing_address' => 'array',
            'shipping_address' => 'array',
            'utm_parameters' => 'array',
            'landing_page_data' => 'array',
            'affiliate_sub_ids' => 'array',
            'placed_at' => 'datetime',
            'paid_at' => 'datetime',
            'fulfilled_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Order $order): void {
            if (blank($order->uuid)) {
                $order->uuid = (string) Str::uuid();
            }
        });
    }

    public function parentOrder(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_order_id');
    }

    public function rebillOrders(): HasMany
    {
        return $this->hasMany(self::class, 'parent_order_id');
    }

    public function sourceSubscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'source_subscription_id');
    }

    public function sourceBillingLedgerEntry(): BelongsTo
    {
        return $this->belongsTo(BillingLedgerEntry::class, 'source_billing_ledger_entry_id');
    }

    public function isRebill(): bool
    {
        return $this->order_type === OrderType::Rebill;
    }

    public function isInitial(): bool
    {
        return $this->order_type === OrderType::Initial;
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function funnel(): BelongsTo
    {
        return $this->belongsTo(Funnel::class);
    }

    public function platformCampaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'platform_campaign_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function fulfillments(): HasMany
    {
        return $this->hasMany(Fulfillment::class);
    }

    public function shipStationIntegration(): HasOne
    {
        return $this->hasOne(ShipStationIntegration::class);
    }

    public function paymentGateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGatewayConfig::class, 'payment_gateway_id');
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function affiliateClick(): BelongsTo
    {
        return $this->belongsTo(AffiliateClick::class, 'affiliate_click_id');
    }

    public function affiliateOffer(): BelongsTo
    {
        return $this->belongsTo(AffiliateOffer::class, 'affiliate_offer_id');
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplicate_of_order_id');
    }

    public function adminActivityLogs(): MorphMany
    {
        return $this->morphMany(AdminActivityLog::class, 'subject')->latest();
    }

    public function resolveRouteBinding($value, $field = null)
    {
        $field = $field ?? $this->getRouteKeyName();

        return static::query()
            ->where($field, $value)
            ->when(
                str_ends_with(request()->path(), '/restore'),
                fn (Builder $query) => $query->withTrashed(),
            )
            ->firstOrFail();
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($term): void {
            $query->where('order_number', 'like', "%{$term}%")
                ->orWhere('customer_email', 'like', "%{$term}%")
                ->orWhereHas('customer', fn (Builder $q) => $q
                    ->where('email', 'like', "%{$term}%")
                    ->orWhere('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%"))
                ->orWhereHas('sourceSubscription', fn (Builder $q) => $q
                    ->where('uuid', 'like', "%{$term}%"))
                ->orWhere('metadata->product_name', 'like', "%{$term}%");
        });
    }

    public function scopeOfType(Builder $query, OrderType $type): Builder
    {
        return $query->where('order_type', $type->value);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
