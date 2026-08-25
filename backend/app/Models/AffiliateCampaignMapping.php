<?php

namespace App\Models;

use App\Modules\Affiliate\Enums\CommissionType;
use App\Policies\AffiliateCampaignMappingPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

#[UsePolicy(AffiliateCampaignMappingPolicy::class)]
class AffiliateCampaignMapping extends Model
{
    protected $fillable = [
        'uuid', 'affiliate_id', 'platform_campaign_id', 'offer_id',
        'commission_type', 'commission_value', 'is_active', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'commission_type' => CommissionType::class,
            'commission_value' => 'decimal:4',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AffiliateCampaignMapping $mapping): void {
            if (blank($mapping->uuid)) {
                $mapping->uuid = (string) Str::uuid();
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

    public function platformCampaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'platform_campaign_id');
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(AffiliateOffer::class, 'offer_id');
    }

    public function adminActivityLogs(): MorphMany
    {
        return $this->morphMany(AdminActivityLog::class, 'subject')->latest();
    }
}
