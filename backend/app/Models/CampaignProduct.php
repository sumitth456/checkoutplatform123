<?php

namespace App\Models;

use App\Modules\Campaign\Enums\CampaignProductInventoryRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CampaignProduct extends Model
{
    protected $table = 'campaign_product';

    protected $fillable = [
        'campaign_id',
        'product_id',
        'variant_id',
        'quantity',
        'sku_override',
        'priority',
        'sort_order',
        'campaign_price',
        'min_quantity',
        'max_quantity',
        'inventory_rule',
        'variant_settings',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'sort_order' => 'integer',
            'quantity' => 'integer',
            'campaign_price' => 'decimal:2',
            'min_quantity' => 'integer',
            'max_quantity' => 'integer',
            'inventory_rule' => CampaignProductInventoryRule::class,
            'variant_settings' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(Variant::class);
    }

    public function billingCycles(): HasMany
    {
        return $this->hasMany(CampaignProductBillingCycle::class)->orderBy('cycle_number');
    }

    public function displaySku(): ?string
    {
        if (filled($this->sku_override)) {
            return $this->sku_override;
        }

        if ($this->relationLoaded('variant') && $this->variant !== null) {
            return $this->variant->sku;
        }

        if ($this->relationLoaded('product') && $this->product !== null) {
            return $this->product->sku;
        }

        return null;
    }
}
