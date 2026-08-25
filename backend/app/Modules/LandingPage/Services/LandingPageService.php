<?php

namespace App\Modules\LandingPage\Services;

use App\Models\Campaign;
use App\Models\LandingPage;
use App\Modules\LandingPage\Enums\LandingPageStatus;
use App\Modules\LandingPage\Support\LandingPageCompiledContentMerger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class LandingPageService
{
    public function __construct(
        private readonly LandingPageVersionService $versions,
        private readonly LandingPageCompiledContentMerger $compiledMerger,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes, ?int $userId = null): LandingPage
    {
        return DB::transaction(function () use ($attributes, $userId): LandingPage {
            $attributes['created_by'] = $userId;
            $attributes['status'] ??= LandingPageStatus::Draft->value;

            $page = LandingPage::query()->create($attributes);
            $this->versions->snapshot($page, 'Initial version', $userId);

            return $page->fresh(['campaign']);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(LandingPage $page, array $attributes, ?int $userId = null, bool $createVersion = true): LandingPage
    {
        return DB::transaction(function () use ($page, $attributes, $userId, $createVersion): LandingPage {
            $page->update($attributes);
            $page->update(['last_auto_saved_at' => now()]);

            if ($createVersion) {
                $this->versions->snapshot($page, 'Saved', $userId);
            }

            return $page->fresh(['campaign']);
        });
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
    public function autoSave(LandingPage $page, array $content, ?int $userId = null): LandingPage
    {
        return DB::transaction(function () use ($page, $content, $userId): LandingPage {
            if (isset($content['builder_json']) && is_array($content['builder_json'])) {
                $content = $this->compiledMerger->mergeCompiledFromBuilder($this->withStoredContentBaseline($page, $content));
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
                return $page;
            }

            $page->update($payload + ['last_auto_saved_at' => now()]);

            $latest = $page->versions()->orderByDesc('version_number')->first();
            $shouldSnapshot = $latest === null
                || $latest->created_at === null
                || $latest->created_at->lt(now()->subMinutes((int) config('landing_pages.auto_save_version_interval_minutes', 5)));

            if ($shouldSnapshot) {
                $this->versions->snapshot($page->fresh(), 'Auto-save', $userId);
            }

            return $page->fresh(['campaign']);
        });
    }

    /**
     * @param  array<string, mixed>  $content
     */
    public function saveBuilderVersion(LandingPage $page, array $content, ?int $userId = null): LandingPage
    {
        if (isset($content['builder_json']) && is_array($content['builder_json'])) {
            $content = $this->compiledMerger->mergeCompiledFromBuilder($this->withStoredContentBaseline($page, $content));
        }

        return $this->update($page, $content, $userId, createVersion: true);
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    private function withStoredContentBaseline(LandingPage $page, array $content): array
    {
        return array_merge([
            'content_html' => (string) ($page->content_html ?? ''),
            'content_css' => (string) ($page->content_css ?? ''),
            'content_js' => (string) ($page->content_js ?? ''),
            'custom_header_scripts' => (string) ($page->custom_header_scripts ?? ''),
            'custom_footer_scripts' => (string) ($page->custom_footer_scripts ?? ''),
        ], $content);
    }

    public function duplicate(LandingPage $page, ?int $userId = null): LandingPage
    {
        $copy = $page->replicate(['uuid', 'slug', 'status', 'published_at', 'archived_at', 'view_count', 'conversion_count', 'revenue_total']);
        $copy->name = $page->name.' (Copy)';
        $copy->slug = LandingPage::uniqueSlug($copy->name);
        $copy->status = LandingPageStatus::Draft;
        $copy->published_at = null;
        $copy->archived_at = null;
        $copy->view_count = 0;
        $copy->conversion_count = 0;
        $copy->revenue_total = 0;
        $copy->created_by = $userId;
        $copy->save();

        $this->versions->snapshot($copy, 'Duplicated from '.$page->uuid, $userId);

        return $copy->fresh(['campaign']);
    }

    public function publish(LandingPage $page, ?int $userId = null): LandingPage
    {
        if ($page->campaign_id === null) {
            throw new InvalidArgumentException('Assign a campaign before publishing.');
        }

        Campaign::query()->findOrFail($page->campaign_id);

        $page->update([
            'status' => LandingPageStatus::Published,
            'published_at' => now(),
            'archived_at' => null,
        ]);

        $this->versions->snapshot($page, 'Published', $userId);

        return $page->fresh();
    }

    public function unpublish(LandingPage $page, ?int $userId = null): LandingPage
    {
        $page->update([
            'status' => LandingPageStatus::Draft,
        ]);

        $this->versions->snapshot($page, 'Unpublished', $userId);

        return $page->fresh();
    }

    public function archive(LandingPage $page, ?int $userId = null): LandingPage
    {
        $page->update([
            'status' => LandingPageStatus::Archived,
            'archived_at' => now(),
        ]);

        $this->versions->snapshot($page, 'Archived', $userId);

        return $page->fresh();
    }

    public function findPublishedBySlug(string $slug): ?LandingPage
    {
        return LandingPage::query()
            ->where('slug', $slug)
            ->where('status', LandingPageStatus::Published)
            ->with(['campaign'])
            ->first();
    }
}
