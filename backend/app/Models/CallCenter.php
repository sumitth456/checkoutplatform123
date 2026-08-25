<?php

namespace App\Models;

use App\Modules\CallCenter\Enums\CallCenterStatus;
use App\Policies\CallCenterPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[UsePolicy(CallCenterPolicy::class)]
class CallCenter extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'description',
        'status',
        'timezone',
        'phone_number',
        'extension',
        'country_code',
        'default_language',
        'allowed_campaign_ids',
        'assigned_user_ids',
        'assigned_role_ids',
        'permissions',
        'created_by',
        'updated_by',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => CallCenterStatus::class,
            'allowed_campaign_ids' => 'array',
            'assigned_user_ids' => 'array',
            'assigned_role_ids' => 'array',
            'permissions' => 'array',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CallCenter $center): void {
            if (blank($center->uuid)) {
                $center->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function agents(): HasMany
    {
        return $this->hasMany(CallCenterAgent::class);
    }

    public function agentGroups(): HasMany
    {
        return $this->hasMany(CallCenterAgentGroup::class);
    }

    public function callLogs(): HasMany
    {
        return $this->hasMany(CallLog::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function adminActivityLogs(): MorphMany
    {
        return $this->morphMany(AdminActivityLog::class, 'subject')->latest();
    }
}
