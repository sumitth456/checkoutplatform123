<?php

namespace App\Models;

use App\Modules\Affiliate\Enums\AffiliatePaymentMethod;
use App\Modules\Affiliate\Enums\AffiliateStatus;
use App\Modules\Affiliate\Enums\CommissionType;
use App\Modules\Affiliate\Enums\RecurringCommissionMode;
use App\Policies\AffiliatePolicy;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Str;

#[UsePolicy(AffiliatePolicy::class)]
class Affiliate extends Authenticatable implements FilamentUser
{
    use SoftDeletes;

    protected $fillable = [
        'uuid', 'company', 'name', 'username', 'email', 'password', 'phone', 'status',
        'default_commission_type', 'default_commission_value', 'payment_method',
        'tax_information', 'address', 'country_code', 'notes', 'api_key', 'tracking_id',
        'postback_url', 'allow_coupon_attribution', 'allow_self_referrals',
        'cookie_duration_days', 'recurring_commission_mode', 'recurring_commission_rebill_limit',
        'pending_commission', 'paid_commission', 'lifetime_revenue',
        'total_clicks', 'total_conversions', 'approved_at', 'last_login_at', 'metadata',
    ];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'status' => AffiliateStatus::class,
            'default_commission_type' => CommissionType::class,
            'payment_method' => AffiliatePaymentMethod::class,
            'recurring_commission_mode' => RecurringCommissionMode::class,
            'default_commission_value' => 'decimal:4',
            'pending_commission' => 'decimal:2',
            'paid_commission' => 'decimal:2',
            'lifetime_revenue' => 'decimal:2',
            'allow_coupon_attribution' => 'boolean',
            'allow_self_referrals' => 'boolean',
            'password' => 'hashed',
            'tax_information' => 'array',
            'address' => 'array',
            'metadata' => 'array',
            'approved_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Affiliate $affiliate): void {
            if (blank($affiliate->uuid)) {
                $affiliate->uuid = (string) Str::uuid();
            }
            if (blank($affiliate->api_key)) {
                $affiliate->api_key = 'aff_'.Str::random(32);
            }
            if (blank($affiliate->tracking_id)) {
                do {
                    $affiliate->tracking_id = strtoupper(Str::random(8));
                } while (self::query()->where('tracking_id', $affiliate->tracking_id)->exists());
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'affiliate'
            && $this->status === AffiliateStatus::Active;
    }

    public function offers(): BelongsToMany
    {
        return $this->belongsToMany(AffiliateOffer::class, 'affiliate_offer_assignments', 'affiliate_id', 'offer_id')->withTimestamps();
    }

    public function campaignMappings(): HasMany
    {
        return $this->hasMany(AffiliateCampaignMapping::class);
    }

    public function trackingLinks(): HasMany
    {
        return $this->hasMany(AffiliateTrackingLink::class);
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(AffiliateClick::class);
    }

    public function conversions(): HasMany
    {
        return $this->hasMany(AffiliateConversion::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(AffiliateCommission::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(AffiliatePayout::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function adminActivityLogs(): MorphMany
    {
        return $this->morphMany(AdminActivityLog::class, 'subject')->latest();
    }
}
