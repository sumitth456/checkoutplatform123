<?php

namespace App\Models;

use App\Modules\Ach\Enums\AchProviderStatus;
use App\Modules\Ach\Enums\AchProviderType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AchProvider extends Model
{
    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'provider',
        'status',
        'sandbox_mode',
        'is_default',
        'priority',
        'api_key',
        'secret_key',
        'merchant_id',
        'settings',
        'daily_limit',
        'daily_volume',
        'daily_volume_date',
    ];

    protected function casts(): array
    {
        return [
            'sandbox_mode' => 'boolean',
            'is_default' => 'boolean',
            'api_key' => 'encrypted',
            'secret_key' => 'encrypted',
            'settings' => 'array',
            'daily_volume' => 'decimal:2',
            'daily_volume_date' => 'date',
            'status' => AchProviderStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AchProvider $provider): void {
            if (blank($provider->uuid)) {
                $provider->uuid = (string) Str::uuid();
            }
        });

        static::saved(function (AchProvider $provider): void {
            if ($provider->is_default) {
                static::query()
                    ->where('id', '!=', $provider->id)
                    ->update(['is_default' => false]);
            }
        });
    }

    public function providerType(): AchProviderType
    {
        return AchProviderType::from($this->provider);
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(CustomerPaymentMethod::class, 'ach_provider_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(AchPaymentLog::class);
    }

    public function isEnabled(): bool
    {
        return $this->status === AchProviderStatus::Enabled;
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
