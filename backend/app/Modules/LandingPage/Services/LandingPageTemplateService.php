<?php

namespace App\Modules\LandingPage\Services;

use App\Models\LandingPage;
use App\Models\LandingPageAsset;
use App\Models\LandingPageForm;
use App\Models\LandingPageTemplate;
use App\Modules\LandingPage\Builder\LandingPageBuilderDocument;
use App\Modules\LandingPage\Builder\LandingPageDocumentCompiler;
use App\Modules\LandingPage\Enums\LandingPageStatus;
use App\Modules\LandingPage\Support\LandingPageCompiledContentGuard;
use App\Modules\LandingPage\Support\LandingPageCompiledContentMerger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class LandingPageTemplateService
{
    public function __construct(
        private readonly LandingPageService $pages,
        private readonly LandingPageVersionService $versions,
        private readonly LandingPageFormService $forms,
        private readonly LandingPageDocumentCompiler $compiler,
        private readonly LandingPageCompiledContentMerger $compiledMerger,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     */
    public function createFromPage(LandingPage $page, string $name, array $options = [], ?int $userId = null): LandingPageTemplate
    {
        $name = trim($name);

        if ($name === '') {
            throw new InvalidArgumentException('Template name is required.');
        }

        $page->loadMissing(['forms', 'assets']);

        $payload = $this->snapshotFromPage($page);

        return LandingPageTemplate::query()->create([
            'name' => $name,
            'slug' => $this->uniqueTemplateSlug($name),
            'description' => $options['description'] ?? ('Saved from '.$page->name),
            'category' => $options['category'] ?? null,
            'content_html' => $payload['content_html'],
            'content_css' => $payload['content_css'],
            'content_js' => $payload['content_js'],
            'custom_header_scripts' => $payload['custom_header_scripts'],
            'custom_footer_scripts' => $payload['custom_footer_scripts'],
            'builder_json' => $payload['builder_json'],
            'preview_image_path' => $options['preview_image_path'] ?? $page->og_image_path,
            'metadata' => $payload['metadata'],
            'source_landing_page_id' => $page->id,
            'created_by' => $userId,
            'is_active' => $options['is_active'] ?? true,
        ]);
    }

    public function updateFromPage(LandingPageTemplate $template, LandingPage $page, ?int $userId = null): LandingPageTemplate
    {
        $page->loadMissing(['forms', 'assets']);
        $payload = $this->snapshotFromPage($page);

        $template->update([
            'content_html' => $payload['content_html'],
            'content_css' => $payload['content_css'],
            'content_js' => $payload['content_js'],
            'custom_header_scripts' => $payload['custom_header_scripts'],
            'custom_footer_scripts' => $payload['custom_footer_scripts'],
            'builder_json' => $payload['builder_json'],
            'metadata' => $payload['metadata'],
            'source_landing_page_id' => $page->id,
        ]);

        return $template->fresh();
    }

    public function duplicate(LandingPageTemplate $template, ?int $userId = null): LandingPageTemplate
    {
        $copy = $template->replicate(['uuid', 'slug']);
        $copy->name = $template->name.' Copy';
        $copy->slug = $this->uniqueTemplateSlug($copy->name);
        $copy->created_by = $userId;
        $copy->save();

        return $copy->fresh();
    }

    /**
     * @param  array{
     *     content_html?: string|null,
     *     content_css?: string|null,
     *     content_js?: string|null,
     *     custom_header_scripts?: string|null,
     *     custom_footer_scripts?: string|null,
     *     builder_json?: array|null,
     * }  $content
     */
    public function autoSave(LandingPageTemplate $template, array $content): LandingPageTemplate
    {
        if (isset($content['builder_json']) && is_array($content['builder_json'])) {
            $content = $this->compiledMerger->mergeCompiledFromBuilder($this->withStoredContentBaseline($template, $content));
        }

        $payload = array_intersect_key($content, array_flip([
            'content_html',
            'content_css',
            'content_js',
            'custom_header_scripts',
            'custom_footer_scripts',
            'builder_json',
        ]));

        if ($payload === []) {
            return $template;
        }

        $template->update($payload);

        return $template->fresh();
    }

    public function toggleActive(LandingPageTemplate $template, bool $isActive): LandingPageTemplate
    {
        $template->update(['is_active' => $isActive]);

        return $template->fresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createBlank(array $attributes, ?int $userId = null): LandingPageTemplate
    {
        $name = trim((string) ($attributes['name'] ?? ''));

        if ($name === '') {
            throw new InvalidArgumentException('Template name is required.');
        }

        $sourcePage = null;

        if (filled($attributes['source_landing_page_id'] ?? null)) {
            $sourcePage = LandingPage::query()
                ->with(['forms', 'assets'])
                ->find((int) $attributes['source_landing_page_id']);
        }

        if ($sourcePage !== null) {
            return $this->createFromPage($sourcePage, $name, [
                'category' => $attributes['category'] ?? null,
                'description' => $attributes['description'] ?? null,
                'preview_image_path' => $attributes['preview_image_path'] ?? $sourcePage->og_image_path,
                'is_active' => $attributes['is_active'] ?? true,
            ], $userId);
        }

        return LandingPageTemplate::query()->create([
            'name' => $name,
            'slug' => filled($attributes['slug'] ?? null)
                ? (string) $attributes['slug']
                : $this->uniqueTemplateSlug($name),
            'description' => $attributes['description'] ?? null,
            'category' => $attributes['category'] ?? null,
            'preview_image_path' => $attributes['preview_image_path'] ?? null,
            'builder_json' => LandingPageBuilderDocument::normalize([]),
            'metadata' => [],
            'created_by' => $userId,
            'is_active' => $attributes['is_active'] ?? true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes  name, slug, campaign_id, status, etc.
     */
    public function createPageFromTemplate(LandingPageTemplate $template, array $attributes, ?int $userId = null): LandingPage
    {
        return DB::transaction(function () use ($template, $attributes, $userId): LandingPage {
            $name = trim((string) ($attributes['name'] ?? $template->name));
            $slug = filled($attributes['slug'] ?? null)
                ? (string) $attributes['slug']
                : LandingPage::uniqueSlug($name);

            $builderJson = LandingPageBuilderDocument::normalize(
                LandingPageCompiledContentGuard::deepCopyDocument($template->builder_json),
            );
            $content = [
                'builder_json' => $builderJson,
                'content_html' => (string) ($template->content_html ?? ''),
                'content_css' => (string) ($template->content_css ?? ''),
                'content_js' => (string) ($template->content_js ?? ''),
                'custom_header_scripts' => (string) ($template->custom_header_scripts ?? ''),
                'custom_footer_scripts' => (string) ($template->custom_footer_scripts ?? ''),
            ];

            $page = $this->pages->create([
                'name' => $name,
                'slug' => $slug,
                'status' => $attributes['status'] ?? LandingPageStatus::Draft->value,
                'campaign_id' => $attributes['campaign_id'] ?? null,
                'template_id' => $template->id,
                'seo_title' => $attributes['seo_title'] ?? null,
                'meta_description' => $attributes['meta_description'] ?? null,
                'og_image_path' => $attributes['og_image_path'] ?? $template->preview_image_path,
                ...$content,
            ], $userId);

            $this->cloneFormsFromTemplate($page, $template);
            $this->cloneAssetsFromTemplate($page, $template);

            $this->versions->snapshot($page->fresh(), 'Created from template: '.$template->name, $userId);

            return $page->fresh(['campaign', 'forms', 'assets']);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshotFromPage(LandingPage $page): array
    {
        $builderJson = LandingPageBuilderDocument::normalize($page->builder_json);

        $content = [
            'content_html' => (string) ($page->content_html ?? ''),
            'content_css' => (string) ($page->content_css ?? ''),
            'content_js' => (string) ($page->content_js ?? ''),
            'custom_header_scripts' => (string) ($page->custom_header_scripts ?? ''),
            'custom_footer_scripts' => (string) ($page->custom_footer_scripts ?? ''),
            'builder_json' => $builderJson,
        ];

        $content = $this->compiledMerger->mergeCompiledFromBuilder($content);

        return [
            ...$content,
            'metadata' => [
                'forms' => $page->forms->map(fn (LandingPageForm $form): array => [
                    'name' => $form->name,
                    'form_key' => $form->form_key,
                    'field_schema' => $form->field_schema ?? [],
                    'settings' => $form->settings ?? [],
                    'is_active' => (bool) $form->is_active,
                ])->values()->all(),
                'assets' => $page->assets->map(fn (LandingPageAsset $asset): array => [
                    'uuid' => $asset->uuid,
                    'type' => $asset->type->value,
                    'disk' => $asset->disk,
                    'path' => $asset->path,
                    'filename' => $asset->filename,
                    'mime_type' => $asset->mime_type,
                    'size_bytes' => $asset->size_bytes,
                    'alt_text' => $asset->alt_text,
                    'is_protected' => (bool) $asset->is_protected,
                    'metadata' => $asset->metadata ?? [],
                ])->values()->all(),
                'source_page_uuid' => $page->uuid,
                'responsive_settings' => $builderJson['settings'] ?? [],
            ],
        ];
    }

    private function cloneFormsFromTemplate(LandingPage $page, LandingPageTemplate $template): void
    {
        $forms = $template->metadata['forms'] ?? [];

        if ($forms === []) {
            $this->forms->ensureDefaultCheckoutForm($page);

            return;
        }

        foreach ($forms as $formData) {
            $page->forms()->create([
                'name' => $formData['name'] ?? 'Checkout',
                'form_key' => $formData['form_key'] ?? 'checkout-form',
                'field_schema' => $formData['field_schema'] ?? [],
                'settings' => $formData['settings'] ?? [],
                'is_active' => (bool) ($formData['is_active'] ?? true),
            ]);
        }
    }

    private function cloneAssetsFromTemplate(LandingPage $page, LandingPageTemplate $template): void
    {
        $assets = $this->resolveAssetSnapshots($template);

        if ($assets === []) {
            return;
        }

        $uuidMap = [];

        foreach ($assets as $snapshot) {
            $oldUuid = (string) ($snapshot['uuid'] ?? '');

            if ($oldUuid === '' || blank($snapshot['path'] ?? null)) {
                continue;
            }

            $disk = (string) ($snapshot['disk'] ?? 'public');
            $path = (string) $snapshot['path'];

            if (! Storage::disk($disk)->exists($path)) {
                continue;
            }

            $extension = pathinfo($path, PATHINFO_EXTENSION);
            $newPath = 'landing-pages/assets/page-'.$page->id.'/'.Str::uuid().($extension ? '.'.$extension : '');

            Storage::disk($disk)->copy($path, $newPath);

            $asset = $page->assets()->create([
                'type' => $snapshot['type'],
                'disk' => $disk,
                'path' => $newPath,
                'filename' => $snapshot['filename'] ?? basename($newPath),
                'mime_type' => $snapshot['mime_type'] ?? null,
                'size_bytes' => (int) ($snapshot['size_bytes'] ?? 0),
                'alt_text' => $snapshot['alt_text'] ?? null,
                'is_protected' => (bool) ($snapshot['is_protected'] ?? false),
                'metadata' => $snapshot['metadata'] ?? [],
            ]);

            $uuidMap[$oldUuid] = $asset->uuid;
        }

        if ($uuidMap === []) {
            return;
        }

        $page->update([
            'content_html' => $this->replaceAssetUuids((string) $page->content_html, $uuidMap),
            'content_css' => $this->replaceAssetUuids((string) $page->content_css, $uuidMap),
            'content_js' => $this->replaceAssetUuids((string) $page->content_js, $uuidMap),
            'custom_header_scripts' => $this->replaceAssetUuids((string) $page->custom_header_scripts, $uuidMap),
            'custom_footer_scripts' => $this->replaceAssetUuids((string) $page->custom_footer_scripts, $uuidMap),
            'builder_json' => json_decode(
                $this->replaceAssetUuids(json_encode($page->builder_json ?? []), $uuidMap),
                true,
            ),
        ]);
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    private function withStoredContentBaseline(LandingPageTemplate $template, array $content): array
    {
        return array_merge([
            'content_html' => (string) ($template->content_html ?? ''),
            'content_css' => (string) ($template->content_css ?? ''),
            'content_js' => (string) ($template->content_js ?? ''),
            'custom_header_scripts' => (string) ($template->custom_header_scripts ?? ''),
            'custom_footer_scripts' => (string) ($template->custom_footer_scripts ?? ''),
        ], $content);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function resolveAssetSnapshots(LandingPageTemplate $template): array
    {
        $assets = $template->metadata['assets'] ?? [];

        if ($assets !== []) {
            return $assets;
        }

        if ($template->source_landing_page_id === null) {
            return [];
        }

        return LandingPageAsset::query()
            ->where('landing_page_id', $template->source_landing_page_id)
            ->get()
            ->map(fn (LandingPageAsset $asset): array => [
                'uuid' => $asset->uuid,
                'type' => $asset->type->value,
                'disk' => $asset->disk,
                'path' => $asset->path,
                'filename' => $asset->filename,
                'mime_type' => $asset->mime_type,
                'size_bytes' => $asset->size_bytes,
                'alt_text' => $asset->alt_text,
                'is_protected' => (bool) $asset->is_protected,
                'metadata' => $asset->metadata ?? [],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, string>  $uuidMap
     */
    private function replaceAssetUuids(string $content, array $uuidMap): string
    {
        foreach ($uuidMap as $oldUuid => $newUuid) {
            $content = str_replace($oldUuid, $newUuid, $content);
        }

        return $content;
    }

    private function uniqueTemplateSlug(string $name): string
    {
        $slug = Str::slug($name);
        $original = $slug;
        $counter = 1;

        while (LandingPageTemplate::query()->where('slug', $slug)->exists()) {
            $slug = "{$original}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
