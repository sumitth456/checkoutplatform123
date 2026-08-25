<?php

namespace App\Models;

use App\Modules\Campaign\Enums\CampaignFunnelMappingStatus;
use App\Policies\CampaignFunnelMappingPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[UsePolicy(CampaignFunnelMappingPolicy::class)]
class CampaignFunnelMapping extends Model
{
    protected $fillable = [
        'uuid',
        'campaign_id',
        'funnel_id',
        'landing_url',
        'api_key_encrypted',
        'api_key_preview',
        'status',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'status' => CampaignFunnelMappingStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CampaignFunnelMapping $mapping): void {
            if (blank($mapping->uuid)) {
                $mapping->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function funnel(): BelongsTo
    {
        return $this->belongsTo(Funnel::class);
    }
}
