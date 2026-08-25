<?php

namespace App\Models;

use App\Modules\CallCenter\Enums\CallStatus;
use App\Modules\CallCenter\Enums\CallType;
use App\Policies\CallLogPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[UsePolicy(CallLogPolicy::class)]
class CallLog extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'call_id',
        'customer_id',
        'customer_name',
        'phone_number',
        'agent_id',
        'call_center_id',
        'platform_campaign_id',
        'disposition_id',
        'call_type',
        'call_status',
        'duration_seconds',
        'recording_url',
        'notes',
        'metadata',
        'started_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'call_type' => CallType::class,
            'call_status' => CallStatus::class,
            'metadata' => 'array',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CallLog $log): void {
            if (blank($log->uuid)) {
                $log->uuid = (string) Str::uuid();
            }

            if (blank($log->call_id)) {
                $log->call_id = 'CALL-'.now()->format('Ymd').'-'.strtoupper(substr(uniqid(), -6));
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(CallCenterAgent::class, 'agent_id');
    }

    public function callCenter(): BelongsTo
    {
        return $this->belongsTo(CallCenter::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'platform_campaign_id');
    }

    public function disposition(): BelongsTo
    {
        return $this->belongsTo(CallDisposition::class);
    }

    public function adminActivityLogs(): MorphMany
    {
        return $this->morphMany(AdminActivityLog::class, 'subject')->latest();
    }

    public function formattedDuration(): string
    {
        $seconds = max(0, (int) $this->duration_seconds);
        $minutes = intdiv($seconds, 60);
        $remaining = $seconds % 60;

        return sprintf('%d:%02d', $minutes, $remaining);
    }
}
