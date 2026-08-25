<?php

namespace App\Models;

use App\Modules\Campaign\Enums\CampaignDiscountType;
use App\Modules\Campaign\Enums\CampaignOfferTrigger;
use App\Policies\CampaignMerchandisingPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[UsePolicy(CampaignMerchandisingPolicy::class)]
class CampaignDownsell extends Model
{
    protected $fillable = [
        'uuid',
        'campaign_id',
        'product_id',
        'priority',
        'sort_order',
        'trigger',
        'trigger_config',
        'discount_type',
        'discount_value',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'sort_order' => 'integer',
            'trigger' => CampaignOfferTrigger::class,
            'trigger_config' => 'array',
            'discount_type' => CampaignDiscountType::class,
            'discount_value' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CampaignDownsell $row): void {
            if (blank($row->uuid)) {
                $row->uuid = (string) Str::uuid();
            }
        });
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
