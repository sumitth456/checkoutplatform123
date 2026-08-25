<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class RetryPolicy extends Model
{
    use HasFactory;
    protected $fillable = [
        'uuid',
        'name',
        'max_attempts',
        'retry_offsets',
    ];

    protected function casts(): array
    {
        return [
            'retry_offsets' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (RetryPolicy $policy): void {
            if (blank($policy->uuid)) {
                $policy->uuid = (string) Str::uuid();
            }
        });
    }

    public function plans(): HasMany
    {
        return $this->hasMany(SubscriptionPlan::class);
    }
}
