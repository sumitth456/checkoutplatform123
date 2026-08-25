<?php

namespace App\Models;

use App\Modules\Campaign\Enums\CampaignOrderBumpDisplayRule;
use App\Modules\Campaign\Enums\CampaignOfferPosition;
use App\Policies\CampaignMerchandisingPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[UsePolicy(CampaignMerchandisingPolicy::class)]
class CampaignOrderBump extends Model
{
    protected $fillable = [
        'uuid',
        'campaign_id',
        'product_id',
        'priority',
        'sort_order',
        'bump_price',
        'position',
        'display_rule',
        'display_rule_config',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'sort_order' => 'integer',
            'bump_price' => 'decimal:2',
            'position' => CampaignOfferPosition::class,
            'display_rule' => CampaignOrderBumpDisplayRule::class,
            'display_rule_config' => 'array',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CampaignOrderBump $row): void {
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
