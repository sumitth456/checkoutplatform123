<?php

namespace App\Models;

use App\Modules\Affiliate\Enums\CommissionStatus;
use App\Modules\Affiliate\Enums\CommissionType;
use App\Policies\AffiliateCommissionPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

#[UsePolicy(AffiliateCommissionPolicy::class)]
class AffiliateCommission extends Model
{
    protected $fillable = [
        'uuid', 'affiliate_id', 'conversion_id', 'order_id', 'payout_id', 'status',
        'commission_type', 'commission_rate', 'order_amount', 'commission_amount',
        'currency', 'rule_source', 'rebill_number', 'notes', 'earned_at',
        'approved_at', 'paid_at', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => CommissionStatus::class,
            'commission_type' => CommissionType::class,
            'commission_rate' => 'decimal:4',
            'order_amount' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'earned_at' => 'datetime',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AffiliateCommission $commission): void {
            if (blank($commission->uuid)) {
                $commission->uuid = (string) Str::uuid();
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

    public function conversion(): BelongsTo
    {
        return $this->belongsTo(AffiliateConversion::class, 'conversion_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(AffiliatePayout::class, 'payout_id');
    }

    public function adminActivityLogs(): MorphMany
    {
        return $this->morphMany(AdminActivityLog::class, 'subject')->latest();
    }
}
