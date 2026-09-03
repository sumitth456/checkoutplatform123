<?php

namespace App\Modules\LandingPage\Support;

use Illuminate\Support\Str;

final class TshirtLandingPageSlugValidator
{
    public const PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public static function normalize(?string $slug): ?string
    {
        if ($slug === null) {
            return null;
        }

        $normalized = Str::slug(strtolower(trim($slug)));

        return $normalized !== '' ? $normalized : null;
    }

    public static function isValidFormat(?string $slug): bool
    {
        $slug = static::normalize($slug);

        if ($slug === null || strlen($slug) > 120) {
            return false;
        }

        return (bool) preg_match(self::PATTERN, $slug);
    }

    /**
     * @return list<string>
     */
    public static function validationErrors(?string $slug): array
    {
        $original = trim((string) $slug);

        if ($original === '') {
            return ['The landing page slug is required.'];
        }

        if (preg_match('/[\s]/', $original) !== 0) {
            return ['The slug cannot contain spaces.'];
        }

        if (str_contains($original, '://') || str_contains($original, '/') || str_contains($original, '?') || str_contains($original, '#') || str_contains($original, '.')) {
            return ['The slug cannot contain URLs, dots, slashes, or query strings.'];
        }

        $normalized = static::normalize($slug);

        if ($normalized === null || ! preg_match(self::PATTERN, $normalized)) {
            return ['The slug may only contain lowercase letters, numbers, and hyphens.'];
        }

        if (strlen($normalized) > 120) {
            return ['The slug may not be greater than 120 characters.'];
        }

        if (TshirtLandingPageReservedSlugs::isReserved($normalized)) {
            return ['This slug is reserved and cannot be used for a T-shirt landing page.'];
        }

        return [];
    }

    public static function isValid(?string $slug): bool
    {
        return static::validationErrors($slug) === [];
    }
}
