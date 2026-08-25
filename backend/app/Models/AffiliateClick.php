<?php

namespace App\Models;

use App\Policies\AffiliateClickPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[UsePolicy(AffiliateClickPolicy::class)]
class AffiliateClick extends Model
{
    protected $fillable = [
        'uuid', 'click_id', 'affiliate_id', 'offer_id', 'platform_campaign_id',
        'tracking_link_id', 'landing_page_id', 'sub_id_1', 'sub_id_2', 'sub_id_3',
        'sub_id_4', 'sub_id_5', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content',
        'ip_address', 'user_agent', 'browser', 'os', 'device_type', 'country_code',
        'landing_url', 'referrer_url', 'is_unique', 'clicked_at', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_unique' => 'boolean',
            'clicked_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AffiliateClick $click): void {
            if (blank($click->uuid)) {
                $click->uuid = (string) Str::uuid();
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

    public function trackingLink(): BelongsTo
    {
        return $this->belongsTo(AffiliateTrackingLink::class, 'tracking_link_id');
    }

    public function landingPage(): BelongsTo
    {
        return $this->belongsTo(LandingPage::class, 'landing_page_id');
    }
}
