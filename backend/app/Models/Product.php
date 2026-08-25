<?php

namespace App\Models;

use App\Modules\Product\Enums\ProductStatus;
use App\Modules\Product\Enums\ProductType;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Product extends Model
{
    use SoftDeletes;

    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'description',
        'short_description',
        'category_id',
        'brand_id',
        'type',
        'status',
        'sku',
        'currency',
        'base_price',
        'compare_at_price',
        'cost',
        'weight',
        'is_taxable',
        'requires_shipping',
        'is_subscription',
        'subscription_plan_id',
        'billing_interval',
        'billing_interval_count',
        'trial_days',
        'images',
        'metadata',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => ProductType::class,
            'status' => ProductStatus::class,
            'base_price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'cost' => 'decimal:2',
            'weight' => 'decimal:3',
            'is_taxable' => 'boolean',
            'requires_shipping' => 'boolean',
            'is_subscription' => 'boolean',
            'billing_interval_count' => 'integer',
            'trial_days' => 'integer',
            'images' => 'array',
            'metadata' => 'array',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Product $product): void {
            if (blank($product->uuid)) {
                $product->uuid = (string) Str::uuid();
            }

            if (blank($product->slug) && filled($product->name)) {
                $product->slug = static::uniqueSlug($product->name);
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

    public function variants(): HasMany
    {
        return $this->hasMany(Variant::class);
    }

    public function billingCycles(): HasMany
    {
        return $this->hasMany(ProductBillingCycle::class)->orderBy('cycle_number');
    }

    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(ProductBrand::class, 'brand_id');
    }

    public function funnels(): BelongsToMany
    {
        return $this->belongsToMany(Funnel::class, 'funnel_product')
            ->withPivot(['sort_order', 'campaign_price', 'is_active'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function campaigns(): BelongsToMany
    {
        return $this->belongsToMany(Campaign::class, 'campaign_product')
            ->using(CampaignProductPivot::class)
            ->withPivot([
                'priority',
                'sort_order',
                'variant_id',
                'quantity',
                'sku_override',
                'campaign_price',
                'min_quantity',
                'max_quantity',
                'inventory_rule',
                'variant_settings',
                'is_active',
            ])
            ->withTimestamps()
            ->orderByPivot('priority', 'desc')
            ->orderByPivot('sort_order');
    }

    public function relatedProducts(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'product_related',
            'product_id',
            'related_product_id',
        )
            ->withPivot(['sort_order'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function adminActivityLogs(): MorphMany
    {
        return $this->morphMany(AdminActivityLog::class, 'subject')->latest();
    }

    public function totalInventoryQuantity(): int
    {
        return (int) $this->variants()->sum('inventory_quantity');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ProductStatus::Active);
    }

    public function scopeOfType(Builder $query, ProductType|string $type): Builder
    {
        return $query->where('type', $type instanceof ProductType ? $type->value : $type);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($term): void {
            $query->where('name', 'like', "%{$term}%")
                ->orWhere('slug', 'like', "%{$term}%")
                ->orWhere('sku', 'like', "%{$term}%");
        });
    }

    protected static function uniqueSlug(string $name): string
    {
        $slug = Str::slug($name);
        $originalSlug = $slug;
        $counter = 1;

        while (static::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$originalSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
