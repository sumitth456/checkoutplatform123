<?php

namespace App\Models;

use App\Policies\WarehouseInventoryPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[UsePolicy(WarehouseInventoryPolicy::class)]
class WarehouseInventory extends Model
{
    protected $fillable = [
        'warehouse_id',
        'variant_id',
        'available',
        'reserved',
        'committed',
        'returned',
        'damaged',
    ];

    protected function casts(): array
    {
        return [
            'available' => 'integer',
            'reserved' => 'integer',
            'committed' => 'integer',
            'returned' => 'integer',
            'damaged' => 'integer',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(Variant::class);
    }

    public function adminActivityLogs(): MorphMany
    {
        return $this->morphMany(AdminActivityLog::class, 'subject')->latest();
    }
}
