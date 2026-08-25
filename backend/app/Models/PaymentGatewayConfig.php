<?php

namespace App\Models;

use App\Modules\Payment\Enums\GatewayWebhookStatus;
use App\Policies\PaymentGatewayPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[UsePolicy(PaymentGatewayPolicy::class)]
class PaymentGatewayConfig extends Model
{
    use SoftDeletes;

    protected $table = 'payment_gateways';

    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'provider',
        'description',
        'credentials',
        'settings',
        'is_active',
        'is_default',
        'supports_subscriptions',
        'supports_refunds',
        'supports_partial_refunds',
        'test_mode',
        'sort_order',
        'weight',
        'daily_limit_amount',
        'monthly_limit_amount',
        'failover_enabled',
        'webhook_status',
        'webhook_verified_at',
        'webhook_last_error',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'settings' => 'array',
            'metadata' => 'array',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'supports_subscriptions' => 'boolean',
            'supports_refunds' => 'boolean',
            'supports_partial_refunds' => 'boolean',
            'test_mode' => 'boolean',
            'failover_enabled' => 'boolean',
            'daily_limit_amount' => 'decimal:2',
            'monthly_limit_amount' => 'decimal:2',
            'webhook_status' => GatewayWebhookStatus::class,
            'webhook_verified_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PaymentGatewayConfig $gateway): void {
            if (blank($gateway->uuid)) {
                $gateway->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function adminActivityLogs(): MorphMany
    {
        return $this->morphMany(AdminActivityLog::class, 'subject')->latest();
    }

    /**
     * @return array<string, mixed>
     */
    public function getRetrySettingsAttribute(): array
    {
        return (array) ($this->settings['retry'] ?? []);
    }
}
