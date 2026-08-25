<?php

namespace App\Modules\LandingPage\Support;

use App\Models\LandingPage;

final class LandingPageBuilderPreviewDocument
{
    public static function fromParts(
        string $header,
        string $css,
        string $html,
        string $js,
        string $footer,
    ): string {
        return implode("\n", [
            '<!DOCTYPE html><html><head><meta charset="utf-8">',
            $header,
            '<style>',
            $css,
            '</style></head><body>',
            $html,
            '<script>',
            $js,
            '</script>',
            $footer,
            '</body></html>',
        ]);
    }

    public static function fromLandingPage(LandingPage $page): string
    {
        return self::fromParts(
            (string) ($page->custom_header_scripts ?? ''),
            (string) ($page->content_css ?? ''),
            (string) ($page->content_html ?? ''),
            (string) ($page->content_js ?? ''),
            (string) ($page->custom_footer_scripts ?? ''),
        );
    }

    public static function fromTemplate(\App\Models\LandingPageTemplate $template): string
    {
        return self::fromParts(
            (string) ($template->custom_header_scripts ?? ''),
            (string) ($template->content_css ?? ''),
            (string) ($template->content_html ?? ''),
            (string) ($template->content_js ?? ''),
            (string) ($template->custom_footer_scripts ?? ''),
        );
    }

    public static function cacheKey(int|string $userId, LandingPage $landingPage): string
    {
        return 'lp-builder-preview:'.$userId.':'.$landingPage->getKey();
    }

    public static function templateCacheKey(int|string $userId, \App\Models\LandingPageTemplate $template): string
    {
        return 'lp-template-builder-preview:'.$userId.':'.$template->getKey();
    }
}
