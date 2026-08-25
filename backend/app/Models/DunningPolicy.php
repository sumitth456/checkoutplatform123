<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class DunningPolicy extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'name',
        'stages',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'stages' => 'array',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (DunningPolicy $policy): void {
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
