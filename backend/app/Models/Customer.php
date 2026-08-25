<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Customer extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'user_id',
        'email',
        'first_name',
        'last_name',
        'phone',
        'company',
        'tax_id',
        'locale',
        'currency',
        'billing_address',
        'shipping_address',
        'accepts_marketing',
        'is_tax_exempt',
        'status',
        'orders_count',
        'total_spent',
        'first_order_at',
        'last_order_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'billing_address' => 'array',
            'shipping_address' => 'array',
            'accepts_marketing' => 'boolean',
            'is_tax_exempt' => 'boolean',
            'total_spent' => 'decimal:2',
            'first_order_at' => 'datetime',
            'last_order_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Customer $customer): void {
            if (blank($customer->uuid)) {
                $customer->uuid = (string) Str::uuid();
            }
        });
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(CustomerPaymentMethod::class);
    }

    public function achPaymentMethods(): HasMany
    {
        return $this->hasMany(CustomerPaymentMethod::class)->where('payment_method', 'ach');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function adminActivityLogs(): MorphMany
    {
        return $this->morphMany(AdminActivityLog::class, 'subject')->latest();
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function resolveRouteBinding($value, $field = null)
    {
        $field = $field ?? $this->getRouteKeyName();

        return static::query()
            ->where($field, $value)
            ->when(
                str_ends_with(request()->path(), '/restore'),
                fn (Builder $query) => $query->withTrashed(),
            )
            ->firstOrFail();
    }

    public function getFullNameAttribute(): string
    {
        $name = trim((string) $this->first_name.' '.(string) $this->last_name);

        return $name !== '' ? $name : (string) $this->email;
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($term): void {
            $query->where('email', 'like', "%{$term}%")
                ->orWhere('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")
                ->orWhere('company', 'like', "%{$term}%");
        });
    }
}
