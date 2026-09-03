<?php

namespace App\Modules\LandingPage\Support;

final class TshirtLandingPageReservedSlugs
{
    /**
     * @return list<string>
     */
    public static function all(): array
    {
        $configured = config('landing_pages.tshirt.reserved_slugs', []);

        return array_values(array_unique(array_map(
            static fn (string $slug): string => strtolower(trim($slug)),
            is_array($configured) ? $configured : [],
        )));
    }

    public static function isReserved(string $slug): bool
    {
        return in_array(strtolower(trim($slug)), static::all(), true);
    }
}
