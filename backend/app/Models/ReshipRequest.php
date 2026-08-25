<?php

namespace App\Models;

use App\Policies\ReshipRequestPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

#[UsePolicy(ReshipRequestPolicy::class)]
class ReshipRequest extends Model
{
    protected $fillable = [
        'uuid',
        'fulfillment_id',
        'order_id',
        'new_fulfillment_id',
        'reason',
        'status',
        'notes',
        'created_by',
    ];

    protected static function booted(): void
    {
        static::creating(function (ReshipRequest $request): void {
            if (blank($request->uuid)) {
                $request->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function fulfillment(): BelongsTo
    {
        return $this->belongsTo(Fulfillment::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function adminActivityLogs(): MorphMany
    {
        return $this->morphMany(AdminActivityLog::class, 'subject')->latest();
    }
}
