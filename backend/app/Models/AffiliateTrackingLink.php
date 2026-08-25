<?php

namespace App\Models;

use App\Policies\AffiliateTrackingLinkPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[UsePolicy(AffiliateTrackingLinkPolicy::class)]
class AffiliateTrackingLink extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid', 'affiliate_id', 'offer_id', 'platform_campaign_id', 'landing_page_id',
        'code', 'name', 'destination_url', 'generated_url', 'is_active',
        'click_count', 'conversion_count', 'default_sub_ids', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'default_sub_ids' => 'array',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (AffiliateTrackingLink $link): void {
            if (blank($link->uuid)) {
                $link->uuid = (string) Str::uuid();
            }

            if (blank($link->code)) {
                do {
                    $link->code = strtoupper(Str::random(8));
                } while (self::query()->where('code', $link->code)->exists());
            }

            $link->generated_url = self::buildGeneratedUrl($link);
        });
    }

    public static function buildGeneratedUrl(self $link): string
    {
        $destination = $link->destination_url ?: rtrim((string) config('app.url'), '/');

        $affiliate = $link->relationLoaded('affiliate')
            ? $link->affiliate
            : ($link->affiliate_id ? Affiliate::query()->find($link->affiliate_id) : null);

        $query = array_filter([
            'aff' => $affiliate?->tracking_id,
            'offer' => $link->offer_id,
            'campaign' => $link->platform_campaign_id,
            'lp' => $link->landing_page_id,
            'code' => $link->code,
        ], fn (mixed $value): bool => filled($value));

        if (is_array($link->default_sub_ids)) {
            foreach ($link->default_sub_ids as $key => $value) {
                if (filled($value)) {
                    $query[(string) $key] = $value;
                }
            }
        }

        if ($query === []) {
            return $destination;
        }

        $separator = str_contains($destination, '?') ? '&' : '?';

        return $destination.$separator.http_build_query($query);
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

    public function landingPage(): BelongsTo
    {
        return $this->belongsTo(LandingPage::class, 'landing_page_id');
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(AffiliateClick::class, 'tracking_link_id');
    }

    public function adminActivityLogs(): MorphMany
    {
        return $this->morphMany(AdminActivityLog::class, 'subject')->latest();
    }
}
