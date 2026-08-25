<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class SubscriptionPlan extends Model
{
    use HasFactory;
    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'currency',
        'retry_policy_id',
        'dunning_policy_id',
        'payment_gateway_id',
        'is_active',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SubscriptionPlan $plan): void {
            if (blank($plan->uuid)) {
                $plan->uuid = (string) Str::uuid();
            }
        });
    }

    public function steps(): HasMany
    {
        return $this->hasMany(SubscriptionPlanStep::class)->orderBy('step_order');
    }

    public function retryPolicy(): BelongsTo
    {
        return $this->belongsTo(RetryPolicy::class);
    }

    public function dunningPolicy(): BelongsTo
    {
        return $this->belongsTo(DunningPolicy::class);
    }

    public function paymentGateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGatewayConfig::class, 'payment_gateway_id');
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
