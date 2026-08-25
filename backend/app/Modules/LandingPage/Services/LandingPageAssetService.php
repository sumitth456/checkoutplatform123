<?php

namespace App\Modules\LandingPage\Services;

use App\Models\LandingPage;
use App\Models\LandingPageAsset;
use App\Modules\LandingPage\Enums\LandingPageAssetType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LandingPageAssetService
{
    public function inferTypeFromMime(?string $mime, ?string $filename = null): LandingPageAssetType
    {
        $mime = strtolower((string) $mime);
        $extension = strtolower(pathinfo((string) $filename, PATHINFO_EXTENSION));

        return match (true) {
            str_starts_with($mime, 'image/svg') || $extension === 'svg' => LandingPageAssetType::Svg,
            str_starts_with($mime, 'image/') => LandingPageAssetType::Image,
            str_starts_with($mime, 'video/') => LandingPageAssetType::Video,
            $mime === 'text/css' || $extension === 'css' => LandingPageAssetType::Css,
            in_array($mime, ['application/javascript', 'text/javascript'], true) || $extension === 'js' => LandingPageAssetType::Js,
            in_array($mime, ['font/woff', 'font/woff2', 'application/font-woff', 'application/x-font-ttf', 'font/ttf', 'font/otf'], true)
                || in_array($extension, ['woff', 'woff2', 'ttf', 'otf', 'eot'], true) => LandingPageAssetType::Font,
            default => LandingPageAssetType::Image,
        };
    }

    public function validateUpload(LandingPageAssetType $type, ?string $mime, int $sizeBytes): void
    {
        $mime = strtolower((string) $mime);

        if ($sizeBytes > $type->maxSizeBytes()) {
            throw new InvalidArgumentException(sprintf(
                'File exceeds maximum size of %s for %s assets.',
                $this->formatBytes($type->maxSizeBytes()),
                $type->label(),
            ));
        }

        $allowed = $type->allowedMimeTypes();

        if ($mime !== '' && ! in_array($mime, $allowed, true)) {
            throw new InvalidArgumentException(sprintf(
                'MIME type %s is not allowed for %s assets.',
                $mime,
                $type->label(),
            ));
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function finalizeFormData(array $data, ?LandingPageAsset $existing = null): array
    {
        $path = (string) ($data['path'] ?? '');
        $type = $data['type'] instanceof LandingPageAssetType
            ? $data['type']
            : LandingPageAssetType::from((string) ($data['type'] ?? LandingPageAssetType::Image->value));

        $isProtected = (bool) ($data['is_protected'] ?? $type->protectedByDefault());
        $disk = $isProtected
            ? (string) config('landing_pages.assets.protected_disk', 'local')
            : (string) config('landing_pages.assets.public_disk', 'public');

        if ($path !== '') {
            $filename = (string) ($data['filename'] ?? basename($path));
            $mime = Storage::disk($disk)->mimeType($path) ?? null;
            $size = (int) (Storage::disk($disk)->size($path) ?: 0);

            if ($existing === null || $existing->path !== $path) {
                $inferred = $this->inferTypeFromMime($mime, $filename);
                if ($existing === null) {
                    $type = $inferred;
                }
            }

            $this->validateUpload($type, $mime, $size);

            $data['type'] = $type;
            $data['disk'] = $disk;
            $data['filename'] = $filename;
            $data['mime_type'] = $mime;
            $data['size_bytes'] = $size;
            $data['is_protected'] = $isProtected;
        }

        return $data;
    }

    public function deliveryUrl(LandingPageAsset $asset, ?int $ttlMinutes = null): ?string
    {
        if (! Storage::disk($asset->disk)->exists($asset->path)) {
            return null;
        }

        if ($asset->is_protected || $asset->disk !== config('landing_pages.assets.public_disk', 'public')) {
            $ttl = $ttlMinutes ?? (int) config('landing_pages.assets.signed_url_ttl_minutes', 1440);

            return URL::temporarySignedRoute(
                'landing-pages.assets.deliver',
                now()->addMinutes($ttl),
                ['asset' => $asset->uuid],
            );
        }

        return Storage::disk($asset->disk)->url($asset->path);
    }

    public function publicEmbedUrl(LandingPageAsset $asset): ?string
    {
        if ($asset->is_protected) {
            return route('landing-pages.assets.deliver', ['asset' => $asset->uuid]);
        }

        return $this->deliveryUrl($asset);
    }

    public function embedSnippet(LandingPageAsset $asset): string
    {
        $url = e($this->publicEmbedUrl($asset) ?? '');

        return match ($asset->type) {
            LandingPageAssetType::Image, LandingPageAssetType::Svg => sprintf(
                '<img src="%s" alt="%s" loading="lazy" />',
                $url,
                e($asset->alt_text ?? $asset->filename),
            ),
            LandingPageAssetType::Video => sprintf(
                '<video src="%s" controls playsinline preload="metadata"></video>',
                $url,
            ),
            LandingPageAssetType::Css => sprintf('<link rel="stylesheet" href="%s" />', $url),
            LandingPageAssetType::Js => sprintf('<script src="%s" defer></script>', $url),
            LandingPageAssetType::Font => sprintf(
                '<!-- Font: %s — add @font-face in CSS pointing to %s -->',
                e($asset->filename),
                $url,
            ),
        };
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function libraryForPage(LandingPage $page): Collection
    {
        return LandingPageAsset::query()
            ->where(function ($query) use ($page): void {
                $query->whereNull('landing_page_id')
                    ->orWhere('landing_page_id', $page->id);
            })
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (LandingPageAsset $asset): array => [
                'uuid' => $asset->uuid,
                'filename' => $asset->filename,
                'type' => $asset->type->value,
                'type_label' => $asset->type->label(),
                'url' => $this->publicEmbedUrl($asset),
                'snippet' => $this->embedSnippet($asset),
                'is_protected' => $asset->is_protected,
                'size' => $this->formatBytes($asset->size_bytes),
            ]);
    }

    public function stream(LandingPageAsset $asset): StreamedResponse
    {
        abort_unless(Storage::disk($asset->disk)->exists($asset->path), 404);

        return Storage::disk($asset->disk)->response(
            $asset->path,
            $asset->filename,
            [
                'Content-Type' => $asset->mime_type ?? 'application/octet-stream',
                'Cache-Control' => $asset->is_protected ? 'private, max-age=3600' : 'public, max-age=86400',
            ],
        );
    }

    public function allowsPublicDelivery(LandingPageAsset $asset, ?string $refererHost): bool
    {
        if (! $asset->is_protected) {
            return true;
        }

        if (blank($refererHost)) {
            return false;
        }

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        return is_string($appHost) && strcasecmp($refererHost, $appHost) === 0;
    }

    public function deleteStoredFile(LandingPageAsset $asset): void
    {
        if ($asset->path !== '' && Storage::disk($asset->disk)->exists($asset->path)) {
            Storage::disk($asset->disk)->delete($asset->path);
        }
    }

    public function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / (1024 * 1024), 2).' MB';
    }

    public function storeUploadedFile(UploadedFile $file, LandingPageAssetType $type, bool $isProtected): string
    {
        $disk = $isProtected
            ? (string) config('landing_pages.assets.protected_disk', 'local')
            : (string) config('landing_pages.assets.public_disk', 'public');

        $directory = 'landing-pages/assets/'.Str::slug($type->value);

        $this->validateUpload($type, $file->getMimeType(), (int) $file->getSize());

        return $file->store($directory, $disk);
    }
}
