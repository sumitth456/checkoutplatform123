<?php

namespace App\Models;

use App\Modules\Product\Import\Enums\ProductImportFormat;
use App\Modules\Product\Import\Enums\ProductImportStatus;
use App\Policies\ProductImportBatchPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[UsePolicy(ProductImportBatchPolicy::class)]
class ProductImportBatch extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'original_filename',
        'disk',
        'path',
        'format',
        'status',
        'headers',
        'column_mapping',
        'preview_rows',
        'stats',
        'target_funnel_id',
        'failure_message',
        'validated_at',
        'queued_at',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'format' => ProductImportFormat::class,
            'status' => ProductImportStatus::class,
            'headers' => 'array',
            'column_mapping' => 'array',
            'preview_rows' => 'array',
            'stats' => 'array',
            'validated_at' => 'datetime',
            'queued_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ProductImportBatch $batch): void {
            if (blank($batch->uuid)) {
                $batch->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function targetFunnel(): BelongsTo
    {
        return $this->belongsTo(Funnel::class, 'target_funnel_id');
    }

    public function errors(): HasMany
    {
        return $this->hasMany(ProductImportError::class);
    }
}
