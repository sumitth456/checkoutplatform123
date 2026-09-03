<?php

namespace App\Modules\LandingPage\Services;

use App\Models\LandingPageSetting;
use App\Modules\LandingPage\Support\TshirtLandingPageSlugValidator;
use Illuminate\Support\Facades\Schema;

final class TshirtLandingPageSettingsService
{
    public const KEY_DOMAIN = 'tshirt_domain';

    public const KEY_DOMAIN_VERIFICATION = 'tshirt_domain_verification';

    public const KEY_WILDCARD_NOTE = 'tshirt_wildcard_note';

    public function configuredDomain(): ?string
    {
        if (Schema::hasTable('landing_page_settings')) {
            $fromDatabase = LandingPageSetting::get(self::KEY_DOMAIN);

            if (is_string($fromDatabase) && filled($fromDatabase)) {
                return $this->normalizeDomain($fromDatabase);
            }
        }

        $fromConfig = config('landing_pages.tshirt.domain');

        return is_string($fromConfig) && filled($fromConfig)
            ? $this->normalizeDomain($fromConfig)
            : null;
    }

    public function saveDomain(?string $domain): void
    {
        if (! Schema::hasTable('landing_page_settings')) {
            return;
        }

        LandingPageSetting::set(
            self::KEY_DOMAIN,
            filled($domain) ? $this->normalizeDomain($domain) : null,
        );
    }

    public function verificationStatus(): string
    {
        if (! Schema::hasTable('landing_page_settings')) {
            return 'pending';
        }

        $stored = LandingPageSetting::get(self::KEY_DOMAIN_VERIFICATION);

        return is_string($stored) && filled($stored) ? $stored : 'pending';
    }

    public function saveVerificationStatus(string $status): void
    {
        if (! Schema::hasTable('landing_page_settings')) {
            return;
        }

        LandingPageSetting::set(self::KEY_DOMAIN_VERIFICATION, $status);
    }

    public function usesHttps(): bool
    {
        return (bool) config('landing_pages.tshirt.use_https', true);
    }

    public function wildcardHostLabel(): string
    {
        $domain = $this->configuredDomain();

        return $domain !== null ? '*.'.$domain : '*.example.com';
    }

    public function isTshirtHostname(string $host): bool
    {
        $domain = $this->configuredDomain();

        if ($domain === null) {
            return false;
        }

        $host = $this->normalizeHost($host);

        if ($host === $domain || $host === 'www.'.$domain) {
            return false;
        }

        return str_ends_with($host, '.'.$domain);
    }

    public function extractSlugFromHost(string $host): ?string
    {
        $domain = $this->configuredDomain();

        if ($domain === null) {
            return null;
        }

        $host = $this->normalizeHost($host);
        $suffix = '.'.$domain;

        if ($host === $domain || $host === 'www.'.$domain || ! str_ends_with($host, $suffix)) {
            return null;
        }

        $slug = substr($host, 0, -strlen($suffix));

        if ($slug === '' || str_contains($slug, '.')) {
            return null;
        }

        if (! TshirtLandingPageSlugValidator::isValid($slug)) {
            return null;
        }

        return $slug;
    }

    public function normalizeDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^https?://#', '', $domain) ?? $domain;
        $domain = rtrim($domain, '/');

        if (str_starts_with($domain, 'www.')) {
            $domain = substr($domain, 4);
        }

        return $domain;
    }

    private function normalizeHost(string $host): string
    {
        $host = strtolower(trim($host));

        return explode(':', $host)[0];
    }
}
