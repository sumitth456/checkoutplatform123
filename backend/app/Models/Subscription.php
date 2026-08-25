<?php

namespace App\Models;

use App\Modules\Subscription\Enums\RebillQueueStatus;
use App\Modules\Subscription\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Subscription extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'customer_id',
        'platform_campaign_id',
        'order_id',
        'order_item_id',
        'product_id',
        'variant_id',
        'subscription_plan_id',
        'current_cycle_step',
        'retry_count',
        'dunning_stage',
        'payment_token',
        'payment_provider',
        'payment_method_type',
        'paused_remaining_days',
        'paused_at',
        'payment_gateway_id',
        'external_id',
        'status',
        'billing_interval',
        'billing_interval_count',
        'currency',
        'amount',
        'quantity',
        'billing_cycle_count',
        'trial_days',
        'trial_ends_at',
        'current_period_starts_at',
        'current_period_ends_at',
        'next_billing_at',
        'next_dunning_at',
        'rebill_queue_status',
        'rebill_skipped_until',
        'started_at',
        'cancelled_at',
        'ends_at',
        'cancellation_reason',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'rebill_queue_status' => RebillQueueStatus::class,
            'rebill_skipped_until' => 'datetime',
            'amount' => 'decimal:2',
            'trial_ends_at' => 'datetime',
            'current_period_starts_at' => 'datetime',
            'current_period_ends_at' => 'datetime',
            'next_billing_at' => 'datetime',
            'next_dunning_at' => 'datetime',
            'started_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'ends_at' => 'datetime',
            'paused_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Subscription $subscription): void {
            if (blank($subscription->uuid)) {
                $subscription->uuid = (string) Str::uuid();
            }
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function platformCampaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'platform_campaign_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function billingLedgerEntries(): HasMany
    {
        return $this->hasMany(BillingLedgerEntry::class)->orderByDesc('attempted_at');
    }

    public function dunningEvents(): HasMany
    {
        return $this->hasMany(SubscriptionDunningEvent::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(Variant::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function paymentGateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGatewayConfig::class, 'payment_gateway_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
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
            $query->where('uuid', 'like', "%{$term}%")
                ->orWhere('external_id', 'like', "%{$term}%")
                ->orWhereHas('customer', fn (Builder $q) => $q->where('email', 'like', "%{$term}%"));
        });
    }

    public function maskedPaymentMethod(): string
    {
        $meta = $this->metadata ?? [];

        if (filled($meta['payment_method_last4'] ?? null)) {
            return trim(($meta['payment_method_brand'] ?? 'Card').' •••• '.$meta['payment_method_last4']);
        }

        if (blank($this->payment_token)) {
            return '—';
        }

        return 'Token •••• '.substr((string) $this->payment_token, -4);
    }

    public function isBillable(): bool
    {
        return in_array($this->status, [
            SubscriptionStatus::Trial,
            SubscriptionStatus::Active,
            SubscriptionStatus::PastDue,
        ], true);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
