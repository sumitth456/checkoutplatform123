<?php

namespace App\Models;

use App\Policies\ShippingRulePolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[UsePolicy(ShippingRulePolicy::class)]
class ShippingRule extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'warehouse_id',
        'fulfillment_house_id',
        'shipping_carrier_id',
        'platform_campaign_id',
        'country_code',
        'state_code',
        'min_weight',
        'max_weight',
        'min_order_value',
        'max_order_value',
        'product_type',
        'subscription_only',
        'shipping_method',
        'priority',
        'conditions',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'is_active' => 'boolean',
            'subscription_only' => 'boolean',
            'priority' => 'integer',
            'min_weight' => 'decimal:3',
            'max_weight' => 'decimal:3',
            'min_order_value' => 'decimal:2',
            'max_order_value' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ShippingRule $rule): void {
            if (blank($rule->uuid)) {
                $rule->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function shippingCarrier(): BelongsTo
    {
        return $this->belongsTo(ShippingCarrier::class);
    }

    public function fulfillmentHouse(): BelongsTo
    {
        return $this->belongsTo(FulfillmentHouse::class);
    }

    public function platformCampaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'platform_campaign_id');
    }

    public function adminActivityLogs(): MorphMany
    {
        return $this->morphMany(AdminActivityLog::class, 'subject')->latest();
    }
}
