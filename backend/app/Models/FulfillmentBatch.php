<?php

namespace App\Models;

use App\Policies\FulfillmentBatchPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

#[UsePolicy(FulfillmentBatchPolicy::class)]
class FulfillmentBatch extends Model
{
    protected $fillable = [
        'uuid',
        'name',
        'status',
        'batch_type',
        'warehouse_id',
        'fulfillment_house_id',
        'fulfillment_count',
        'metadata',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'fulfillment_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (FulfillmentBatch $batch): void {
            if (blank($batch->uuid)) {
                $batch->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function adminActivityLogs(): MorphMany
    {
        return $this->morphMany(AdminActivityLog::class, 'subject')->latest();
    }
}
