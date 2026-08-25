<?php

namespace App\Models;

use App\Modules\LandingPage\Enums\LandingPageAssetType;
use App\Modules\LandingPage\Services\LandingPageAssetService;
use App\Policies\LandingPageAssetPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[UsePolicy(LandingPageAssetPolicy::class)]
class LandingPageAsset extends Model
{
    /** @use HasFactory<\Database\Factories\LandingPageAssetFactory> */
    use HasFactory;

    protected $fillable = [
        'uuid',
        'landing_page_id',
        'type',
        'disk',
        'path',
        'filename',
        'mime_type',
        'size_bytes',
        'alt_text',
        'is_protected',
        'metadata',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => LandingPageAssetType::class,
            'size_bytes' => 'integer',
            'is_protected' => 'boolean',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (LandingPageAsset $asset): void {
            if (blank($asset->uuid)) {
                $asset->uuid = (string) Str::uuid();
            }
        });

        static::deleting(function (LandingPageAsset $asset): void {
            app(LandingPageAssetService::class)->deleteStoredFile($asset);
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function landingPage(): BelongsTo
    {
        return $this->belongsTo(LandingPage::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function publicUrl(): ?string
    {
        return app(LandingPageAssetService::class)->deliveryUrl($this);
    }

    public function embedUrl(): ?string
    {
        return app(LandingPageAssetService::class)->publicEmbedUrl($this);
    }

    public function fileExists(): bool
    {
        return $this->path !== '' && Storage::disk($this->disk)->exists($this->path);
    }
}
