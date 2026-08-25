<?php

namespace App\Models;

use App\Modules\Variant\Enums\InventoryPolicy;
use App\Modules\Variant\Enums\VariantStatus;
use Database\Factories\VariantFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Variant extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'product_id',
        'name',
        'sku',
        'barcode',
        'currency',
        'price',
        'compare_at_price',
        'cost',
        'inventory_quantity',
        'inventory_policy',
        'track_inventory',
        'weight',
        'weight_unit',
        'options',
        'images',
        'is_default',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => VariantStatus::class,
            'inventory_policy' => InventoryPolicy::class,
            'price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'cost' => 'decimal:2',
            'inventory_quantity' => 'integer',
            'track_inventory' => 'boolean',
            'weight' => 'decimal:3',
            'is_default' => 'boolean',
            'options' => 'array',
            'images' => 'array',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Variant $variant): void {
            if (blank($variant->uuid)) {
                $variant->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function resolveRouteBinding($value, $field = null)
    {
        $field = $field ?? $this->getRouteKeyName();

        return static::query()
            ->where($field, $value)
            ->when(
                str_ends_with(request()->path(), '/restore'),
                fn (Builder $query) => $query->withTrashed(),
            )
            ->firstOrFail();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function inventoryReservations(): HasMany
    {
        return $this->hasMany(InventoryReservation::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', VariantStatus::Active);
    }

    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($term): void {
            $query->where('name', 'like', "%{$term}%")
                ->orWhere('sku', 'like', "%{$term}%")
                ->orWhere('barcode', 'like', "%{$term}%");
        });
    }
}
