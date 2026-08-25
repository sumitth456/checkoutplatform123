<?php

namespace App\Models;

use App\Modules\Affiliate\Enums\CommissionType;
use App\Modules\Affiliate\Enums\RecurringCommissionMode;
use App\Policies\AffiliateOfferPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[UsePolicy(AffiliateOfferPolicy::class)]
class AffiliateOffer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid', 'name', 'description', 'commission_type', 'commission_value',
        'allowed_campaign_ids', 'allowed_product_ids', 'allowed_landing_page_ids',
        'recurring_commission_mode', 'recurring_commission_rebill_limit',
        'is_active', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'commission_type' => CommissionType::class,
            'recurring_commission_mode' => RecurringCommissionMode::class,
            'commission_value' => 'decimal:4',
            'allowed_campaign_ids' => 'array',
            'allowed_product_ids' => 'array',
            'allowed_landing_page_ids' => 'array',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AffiliateOffer $offer): void {
            if (blank($offer->uuid)) {
                $offer->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function affiliates(): BelongsToMany
    {
        return $this->belongsToMany(Affiliate::class, 'affiliate_offer_assignments', 'offer_id', 'affiliate_id')->withTimestamps();
    }

    public function campaignMappings(): HasMany
    {
        return $this->hasMany(AffiliateCampaignMapping::class, 'offer_id');
    }

    public function trackingLinks(): HasMany
    {
        return $this->hasMany(AffiliateTrackingLink::class, 'offer_id');
    }

    public function promoMaterials(): HasMany
    {
        return $this->hasMany(AffiliatePromoMaterial::class, 'offer_id');
    }

    public function adminActivityLogs(): MorphMany
    {
        return $this->morphMany(AdminActivityLog::class, 'subject')->latest();
    }
}
