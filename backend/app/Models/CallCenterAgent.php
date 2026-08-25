<?php

namespace App\Models;

use App\Modules\CallCenter\Enums\AgentStatus;
use App\Policies\CallCenterAgentPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[UsePolicy(CallCenterAgentPolicy::class)]
class CallCenterAgent extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'call_center_id',
        'user_id',
        'name',
        'username',
        'email',
        'phone',
        'extension',
        'status',
        'assigned_campaign_ids',
        'role_label',
        'permissions',
        'is_logged_in',
        'last_login_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => AgentStatus::class,
            'assigned_campaign_ids' => 'array',
            'permissions' => 'array',
            'is_logged_in' => 'boolean',
            'last_login_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CallCenterAgent $agent): void {
            if (blank($agent->uuid)) {
                $agent->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function callCenter(): BelongsTo
    {
        return $this->belongsTo(CallCenter::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function callLogs(): HasMany
    {
        return $this->hasMany(CallLog::class, 'agent_id');
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(
            CallCenterAgentGroup::class,
            'call_center_agent_group_members',
            'agent_id',
            'agent_group_id',
        )->withTimestamps();
    }

    public function adminActivityLogs(): MorphMany
    {
        return $this->morphMany(AdminActivityLog::class, 'subject')->latest();
    }
}
