<?php

namespace App\Models;

use App\Modules\Ach\Enums\AchAccountType;
use App\Modules\Ach\Enums\AchHolderType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class CustomerPaymentMethod extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'customer_id',
        'ach_provider_id',
        'payment_method',
        'payment_token',
        'provider',
        'account_type',
        'holder_type',
        'last4',
        'bank_name',
        'is_default',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'metadata' => 'array',
            'account_type' => AchAccountType::class,
            'holder_type' => AchHolderType::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CustomerPaymentMethod $method): void {
            if (blank($method->uuid)) {
                $method->uuid = (string) Str::uuid();
            }
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function achProvider(): BelongsTo
    {
        return $this->belongsTo(AchProvider::class, 'ach_provider_id');
    }

    public function displayLabel(): string
    {
        $type = $this->account_type instanceof AchAccountType
            ? $this->account_type->label()
            : ucfirst((string) $this->account_type);

        return "{$type} ••••{$this->last4}";
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
