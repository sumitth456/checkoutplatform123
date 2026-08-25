<?php

namespace App\Models;

use App\Modules\Order\Enums\FunnelStatus;
use App\Policies\FunnelPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[UsePolicy(FunnelPolicy::class)]
class Funnel extends Model
{
    /** @use HasFactory<\Database\Factories\FunnelFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'platform_campaign_id',
        'name',
        'slug',
        'description',
        'status',
        'entry_product_id',
        'default_payment_gateway_id',
        'currency',
        'settings',
        'tracking',
        'metadata',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'tracking' => 'array',
            'metadata' => 'array',
            'published_at' => 'datetime',
            'status' => FunnelStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Funnel $funnel): void {
            if (blank($funnel->uuid)) {
                $funnel->uuid = (string) Str::uuid();
            }
        });
    }

    public function platformCampaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'platform_campaign_id');
    }

    public function campaignMapping(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(CampaignFunnelMapping::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(FunnelStep::class)->orderBy('step_order');
    }

    public function entryProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'entry_product_id');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'funnel_product')
            ->withPivot(['sort_order', 'campaign_price', 'is_active'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function defaultPaymentGateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGatewayConfig::class, 'default_payment_gateway_id');
    }

    public function adminActivityLogs(): MorphMany
    {
        return $this->morphMany(AdminActivityLog::class, 'subject')->latest();
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
