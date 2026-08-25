<?php

namespace App\Models;

use App\Policies\LandingPageTemplatePolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[UsePolicy(LandingPageTemplatePolicy::class)]
class LandingPageTemplate extends Model
{
    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'description',
        'category',
        'content_html',
        'content_css',
        'content_js',
        'custom_header_scripts',
        'custom_footer_scripts',
        'builder_json',
        'metadata',
        'preview_image_path',
        'source_landing_page_id',
        'created_by',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'builder_json' => 'array',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (LandingPageTemplate $template): void {
            if (blank($template->uuid)) {
                $template->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function landingPages(): HasMany
    {
        return $this->hasMany(LandingPage::class, 'template_id');
    }

    public function sourceLandingPage(): BelongsTo
    {
        return $this->belongsTo(LandingPage::class, 'source_landing_page_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
