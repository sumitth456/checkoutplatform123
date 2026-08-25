<?php

namespace App\Models;

use App\Policies\CallCenterAgentGroupPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[UsePolicy(CallCenterAgentGroupPolicy::class)]
class CallCenterAgentGroup extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'call_center_id',
        'name',
        'description',
        'assigned_campaign_ids',
        'permissions',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'assigned_campaign_ids' => 'array',
            'permissions' => 'array',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CallCenterAgentGroup $group): void {
            if (blank($group->uuid)) {
                $group->uuid = (string) Str::uuid();
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

    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(
            CallCenterAgent::class,
            'call_center_agent_group_members',
            'agent_group_id',
            'agent_id',
        )->withTimestamps();
    }

    public function adminActivityLogs(): MorphMany
    {
        return $this->morphMany(AdminActivityLog::class, 'subject')->latest();
    }
}
