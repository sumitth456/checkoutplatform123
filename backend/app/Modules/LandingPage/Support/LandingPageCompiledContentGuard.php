<?php

namespace App\Modules\LandingPage\Support;

/**
 * Prevents builder recompilation from overwriting legacy/imported page HTML
 * when builder_json only contains placeholder nodes.
 */
final class LandingPageCompiledContentGuard
{
    /**
     * @param  array{html?: string, css?: string, js?: string, header_scripts?: string, footer_scripts?: string}  $compiled
     * @return array{html?: string, css?: string, js?: string, header_scripts?: string, footer_scripts?: string}|null
     */
    public static function mergeWhenAuthoritative(
        string $storedHtml,
        string $storedCss,
        string $storedJs,
        string $storedHeaderScripts,
        string $storedFooterScripts,
        array $compiled,
    ): ?array {
        if (! self::shouldUseCompiledHtml($storedHtml, (string) ($compiled['html'] ?? ''))) {
            return null;
        }

        return [
            'html' => (string) ($compiled['html'] ?? ''),
            'css' => self::shouldUseCompiledCss($storedCss, (string) ($compiled['css'] ?? ''))
                ? (string) ($compiled['css'] ?? '')
                : $storedCss,
            'js' => (string) ($compiled['js'] ?? $storedJs),
            'header_scripts' => (string) ($compiled['header_scripts'] ?? $storedHeaderScripts),
            'footer_scripts' => (string) ($compiled['footer_scripts'] ?? $storedFooterScripts),
        ];
    }

    public static function shouldUseCompiledHtml(string $storedHtml, string $compiledHtml): bool
    {
        $storedLen = strlen(trim($storedHtml));
        $compiledLen = strlen(trim($compiledHtml));

        if ($compiledLen === 0) {
            return false;
        }

        if ($storedLen === 0) {
            return true;
        }

        if ($storedLen > $compiledLen * 2) {
            return false;
        }

        if (self::looksLikeLegacyDocument($storedHtml) && $compiledLen < 2048) {
            return false;
        }

        return true;
    }

    private static function shouldUseCompiledCss(string $storedCss, string $compiledCss): bool
    {
        $storedLen = strlen(trim($storedCss));
        $compiledLen = strlen(trim($compiledCss));

        if ($compiledLen === 0) {
            return false;
        }

        if ($storedLen === 0) {
            return true;
        }

        if ($storedLen > $compiledLen * 2) {
            return false;
        }

        return true;
    }

    private static function looksLikeLegacyDocument(string $html): bool
    {
        $sample = strtolower(substr($html, 0, 4096));

        return str_contains($sample, '<link ')
            || str_contains($sample, '<!doctype')
            || str_contains($sample, 'bootstrap')
            || str_contains($sample, 'data-gjs-sf-plugin');
    }

    /**
     * @param  array<string, mixed>|null  $document
     * @return array<string, mixed>
     */
    public static function deepCopyDocument(?array $document): array
    {
        $decoded = json_decode(json_encode($document ?? []), true);

        return is_array($decoded) ? $decoded : [];
    }
}
