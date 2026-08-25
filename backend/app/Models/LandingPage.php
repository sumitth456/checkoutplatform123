<?php

namespace App\Models;

use App\Modules\LandingPage\Enums\LandingPageStatus;
use App\Policies\LandingPagePolicy;
use Database\Factories\LandingPageFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[UsePolicy(LandingPagePolicy::class)]
class LandingPage extends Model
{
    /** @use HasFactory<LandingPageFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'status',
        'campaign_id',
        'template_id',
        'seo_title',
        'meta_description',
        'og_image_path',
        'content_html',
        'content_css',
        'content_js',
        'custom_header_scripts',
        'custom_footer_scripts',
        'builder_json',
        'view_count',
        'conversion_count',
        'revenue_total',
        'published_at',
        'archived_at',
        'last_auto_saved_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => LandingPageStatus::class,
            'view_count' => 'integer',
            'conversion_count' => 'integer',
            'revenue_total' => 'decimal:2',
            'published_at' => 'datetime',
            'archived_at' => 'datetime',
            'last_auto_saved_at' => 'datetime',
            'builder_json' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (LandingPage $page): void {
            if (blank($page->uuid)) {
                $page->uuid = (string) Str::uuid();
            }

            if (blank($page->slug) && filled($page->name)) {
                $page->slug = static::uniqueSlug($page->name);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function publicUrl(): string
    {
        $prefix = trim((string) config('landing_pages.public_path_prefix', 'offer'), '/');

        return url('/'.$prefix.'/'.$this->slug);
    }

    public function conversionRate(): float
    {
        if ($this->view_count <= 0) {
            return 0.0;
        }

        return round(($this->conversion_count / $this->view_count) * 100, 2);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(LandingPageTemplate::class, 'template_id');
    }

    public function forms(): HasMany
    {
        return $this->hasMany(LandingPageForm::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(LandingPageVersion::class)->orderByDesc('version_number');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(LandingPageAsset::class);
    }

    public function viewEvents(): HasMany
    {
        return $this->hasMany(LandingPageViewEvent::class);
    }

    public function adminActivityLogs(): MorphMany
    {
        return $this->morphMany(AdminActivityLog::class, 'subject')->latest();
    }

    public static function uniqueSlug(string $name): string
    {
        $slug = Str::slug($name);
        $original = $slug;
        $counter = 1;

        while (static::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$original}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
