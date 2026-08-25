<?php

namespace App\Models;

use App\Modules\Affiliate\Enums\ConversionType;
use App\Policies\AffiliateConversionPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[UsePolicy(AffiliateConversionPolicy::class)]
class AffiliateConversion extends Model
{
    protected $fillable = [
        'uuid', 'affiliate_id', 'offer_id', 'platform_campaign_id', 'click_id',
        'order_id', 'customer_id', 'subscription_id', 'conversion_type', 'revenue',
        'commission_amount', 'currency', 'sub_id_1', 'sub_id_2', 'sub_id_3',
        'sub_id_4', 'sub_id_5', 'rebill_number', 'product_ids', 'converted_at', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'conversion_type' => ConversionType::class,
            'revenue' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'product_ids' => 'array',
            'converted_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AffiliateConversion $conversion): void {
            if (blank($conversion->uuid)) {
                $conversion->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(AffiliateOffer::class, 'offer_id');
    }

    public function platformCampaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'platform_campaign_id');
    }

    public function click(): BelongsTo
    {
        return $this->belongsTo(AffiliateClick::class, 'click_id');
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

    public function commissions(): HasMany
    {
        return $this->hasMany(AffiliateCommission::class, 'conversion_id');
    }
}
