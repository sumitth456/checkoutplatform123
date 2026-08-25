<?php

namespace App\Modules\LandingPage\Services;

use App\Models\LandingPage;
use App\Models\LandingPageVersion;
use App\Modules\LandingPage\Enums\LandingPageStatus;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class LandingPageVersionService
{
    public function snapshot(LandingPage $page, ?string $label = null, ?int $userId = null): LandingPageVersion
    {
        $versionNumber = ((int) $page->versions()->max('version_number')) + 1;

        return $page->versions()->create([
            'version_number' => $versionNumber,
            'label' => $label ?? 'Auto-save '.$versionNumber,
            'content_html' => $page->content_html,
            'content_css' => $page->content_css,
            'content_js' => $page->content_js,
            'custom_header_scripts' => $page->custom_header_scripts,
            'custom_footer_scripts' => $page->custom_footer_scripts,
            'builder_json' => $page->builder_json,
            'metadata' => [
                'seo_title' => $page->seo_title,
                'meta_description' => $page->meta_description,
                'slug' => $page->slug,
            ],
            'created_by' => $userId,
        ]);
    }

    public function restore(LandingPage $page, LandingPageVersion $version): LandingPage
    {
        if ($version->landing_page_id !== $page->id) {
            throw new InvalidArgumentException('Version does not belong to this landing page.');
        }

        $page->update([
            'content_html' => $version->content_html,
            'content_css' => $version->content_css,
            'content_js' => $version->content_js,
            'custom_header_scripts' => $version->custom_header_scripts,
            'custom_footer_scripts' => $version->custom_footer_scripts,
            'builder_json' => $version->builder_json,
            'last_auto_saved_at' => now(),
        ]);

        return $page->fresh();
    }
}
