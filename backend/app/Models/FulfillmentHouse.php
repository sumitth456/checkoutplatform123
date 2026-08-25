<?php

namespace App\Models;

use App\Policies\FulfillmentHousePolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[UsePolicy(FulfillmentHousePolicy::class)]
class FulfillmentHouse extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'code',
        'provider_type',
        'status',
        'priority',
        'api_type',
        'api_url',
        'api_key',
        'api_secret',
        'api_username',
        'api_password',
        'webhook_url',
        'supported_countries',
        'supported_shipping_methods',
        'auto_sync',
        'retry_failed_requests',
        'contact_email',
        'contact_phone',
        'api_endpoint',
        'settings',
        'notes',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'supported_countries' => 'array',
            'supported_shipping_methods' => 'array',
            'auto_sync' => 'boolean',
            'retry_failed_requests' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (FulfillmentHouse $house): void {
            if (blank($house->uuid)) {
                $house->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }

    public function adminActivityLogs(): MorphMany
    {
        return $this->morphMany(AdminActivityLog::class, 'subject')->latest();
    }
}
