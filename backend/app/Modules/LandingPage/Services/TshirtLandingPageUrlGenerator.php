<?php

namespace App\Modules\LandingPage\Services;

use App\Models\LandingPage;
use App\Modules\LandingPage\Support\TshirtLandingPageSlugValidator;

final class TshirtLandingPageUrlGenerator
{
    public function __construct(
        private readonly TshirtLandingPageSettingsService $settings,
    ) {}

    public function forLandingPage(LandingPage $page): ?string
    {
        if (! $page->is_tshirt_campaign || blank($page->slug)) {
            return null;
        }

        return $this->forSlug((string) $page->slug);
    }

    public function forSlug(?string $slug): ?string
    {
        $slug = TshirtLandingPageSlugValidator::normalize($slug);
        $domain = $this->settings->configuredDomain();

        if ($slug === null || $domain === null) {
            return null;
        }

        $scheme = $this->settings->usesHttps() ? 'https' : 'http';

        return "{$scheme}://{$slug}.{$domain}";
    }
}
