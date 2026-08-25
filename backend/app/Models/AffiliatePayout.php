<?php

namespace App\Models;

use App\Modules\Affiliate\Enums\AffiliatePaymentMethod;
use App\Modules\Affiliate\Enums\PayoutStatus;
use App\Policies\AffiliatePayoutPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

#[UsePolicy(AffiliatePayoutPolicy::class)]
class AffiliatePayout extends Model
{
    protected $fillable = [
        'uuid', 'affiliate_id', 'status', 'amount', 'currency', 'payment_method',
        'reference', 'notes', 'approved_by', 'approved_at', 'paid_at', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => PayoutStatus::class,
            'payment_method' => AffiliatePaymentMethod::class,
            'amount' => 'decimal:2',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AffiliatePayout $payout): void {
            if (blank($payout->uuid)) {
                $payout->uuid = (string) Str::uuid();
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

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(AffiliateCommission::class, 'payout_id');
    }

    public function adminActivityLogs(): MorphMany
    {
        return $this->morphMany(AdminActivityLog::class, 'subject')->latest();
    }
}
