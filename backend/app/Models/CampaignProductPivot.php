<?php

namespace App\Models;

use App\Modules\Campaign\Enums\CampaignProductInventoryRule;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class CampaignProductPivot extends Pivot
{
    protected $table = 'campaign_product';

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
}
