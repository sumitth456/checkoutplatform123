<?php

namespace App\Models;

use App\Modules\Affiliate\Enums\PromoMaterialType;
use App\Policies\AffiliatePromoMaterialPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[UsePolicy(AffiliatePromoMaterialPolicy::class)]
class AffiliatePromoMaterial extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid', 'name', 'type', 'description', 'file_path', 'file_url',
        'offer_id', 'landing_page_id', 'coupon_id', 'allowed_affiliate_ids',
        'is_active', 'download_count', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'type' => PromoMaterialType::class,
            'allowed_affiliate_ids' => 'array',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AffiliatePromoMaterial $material): void {
            if (blank($material->uuid)) {
                $material->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(AffiliateOffer::class, 'offer_id');
    }

    public function landingPage(): BelongsTo
    {
        return $this->belongsTo(LandingPage::class, 'landing_page_id');
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function adminActivityLogs(): MorphMany
    {
        return $this->morphMany(AdminActivityLog::class, 'subject')->latest();
    }
}
