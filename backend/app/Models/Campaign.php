<?php

namespace App\Models;

use App\Modules\Campaign\Enums\CampaignStatus;
use Database\Factories\CampaignFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Campaign extends Model
{
    /** @use HasFactory<CampaignFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'public_id',
        'name',
        'description',
        'internal_notes',
        'status',
        'brand_id',
        'subscription_plan_id',
        'landing_page_id',
        'store_name',
        'store_code',
        'currency',
        'timezone',
        'metadata',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => CampaignStatus::class,
            'metadata' => 'array',
            'archived_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Campaign $campaign): void {
            if (blank($campaign->uuid)) {
                $campaign->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(ProductBrand::class, 'brand_id');
    }

    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }

    public function landingPage(): BelongsTo
    {
        return $this->belongsTo(LandingPage::class);
    }

    public function campaignProducts(): HasMany
    {
        return $this->hasMany(CampaignProduct::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'campaign_product')
            ->using(CampaignProductPivot::class)
            ->withPivot([
                'priority',
                'sort_order',
                'variant_id',
                'quantity',
                'sku_override',
                'campaign_price',
                'min_quantity',
                'max_quantity',
                'inventory_rule',
                'variant_settings',
                'is_active',
            ])
            ->withTimestamps()
            ->orderByPivot('priority', 'desc')
            ->orderByPivot('sort_order');
    }

    public function funnelMappings(): HasMany
    {
        return $this->hasMany(CampaignFunnelMapping::class);
    }

    public function funnels(): BelongsToMany
    {
        return $this->belongsToMany(Funnel::class, 'campaign_funnel_mappings')
            ->withPivot([
                'uuid',
                'landing_url',
                'api_key_preview',
                'status',
                'sort_order',
            ])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function upsells(): HasMany
    {
        return $this->hasMany(CampaignUpsell::class);
    }

    public function downsells(): HasMany
    {
        return $this->hasMany(CampaignDownsell::class);
    }

    public function orderBumps(): HasMany
    {
        return $this->hasMany(CampaignOrderBump::class);
    }

    public function landingPages(): HasMany
    {
        return $this->hasMany(LandingPage::class);
    }

    public function adminActivityLogs(): MorphMany
    {
        return $this->morphMany(AdminActivityLog::class, 'subject')->latest();
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'campaign_user')->withTimestamps();
    }
}