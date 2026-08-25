<?php

namespace App\Models;

use App\Policies\RmaPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

#[UsePolicy(RmaPolicy::class)]
class Rma extends Model
{
    protected $fillable = [
        'uuid',
        'rma_number',
        'order_id',
        'fulfillment_id',
        'reason',
        'status',
        'notes',
        'restock_inventory',
        'approved_at',
        'received_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'restock_inventory' => 'boolean',
            'approved_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Rma $rma): void {
            if (blank($rma->uuid)) {
                $rma->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function fulfillment(): BelongsTo
    {
        return $this->belongsTo(Fulfillment::class);
    }

    public function adminActivityLogs(): MorphMany
    {
        return $this->morphMany(AdminActivityLog::class, 'subject')->latest();
    }
}
