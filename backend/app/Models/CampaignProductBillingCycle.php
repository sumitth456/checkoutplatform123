<?php

namespace App\Models;

use App\Modules\Campaign\Enums\CampaignProductInventoryRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignProductBillingCycle extends Model
{
    protected $fillable = [
        'campaign_product_id',
        'cycle_number',
        'product_id',
        'variant_id',
        'price',
        'shipping_price',
        'bill_delay_days',
        'delay_fulfillment',
        'shippable',
        'warehouse',
        'subscription_enabled',
        'repeat_forever',
        'inventory_rule',
        'inventory_quantity',
        'coupon_override',
    ];

    protected function casts(): array
    {
        return [
            'cycle_number' => 'integer',
            'price' => 'decimal:2',
            'shipping_price' => 'decimal:2',
            'bill_delay_days' => 'integer',
            'delay_fulfillment' => 'boolean',
            'shippable' => 'boolean',
            'subscription_enabled' => 'boolean',
            'repeat_forever' => 'boolean',
            'inventory_rule' => CampaignProductInventoryRule::class,
            'inventory_quantity' => 'integer',
            'coupon_override' => 'array',
        ];
    }

    public function campaignProduct(): BelongsTo
    {
        return $this->belongsTo(CampaignProduct::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(Variant::class);
    }

    public function label(): string
    {
        if ($this->cycle_number >= 3 && $this->repeat_forever) {
            return "Cycle {$this->cycle_number}+ (repeat forever)";
        }

        return "Cycle {$this->cycle_number}";
    }
}
