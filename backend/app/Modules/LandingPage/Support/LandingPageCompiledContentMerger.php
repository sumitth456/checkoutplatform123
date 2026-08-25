<?php

namespace App\Modules\LandingPage\Support;

use App\Modules\LandingPage\Builder\LandingPageDocumentCompiler;

final class LandingPageCompiledContentMerger
{
    public function __construct(
        private readonly LandingPageDocumentCompiler $compiler,
    ) {}

    /**
     * @param  array<string, mixed>  $content  Must include builder_json and stored content_* fields when applicable.
     * @return array<string, mixed>
     */
    public function mergeCompiledFromBuilder(array $content): array
    {
        if (! isset($content['builder_json']) || ! is_array($content['builder_json'])) {
            return $content;
        }

        $compiled = $this->compiler->compile($content['builder_json']);
        $merged = LandingPageCompiledContentGuard::mergeWhenAuthoritative(
            (string) ($content['content_html'] ?? ''),
            (string) ($content['content_css'] ?? ''),
            (string) ($content['content_js'] ?? ''),
            (string) ($content['custom_header_scripts'] ?? ''),
            (string) ($content['custom_footer_scripts'] ?? ''),
            [
                'html' => $compiled['html'],
                'css' => $compiled['css'],
                'js' => $compiled['js'],
                'header_scripts' => $compiled['header_scripts'],
                'footer_scripts' => $compiled['footer_scripts'],
            ],
        );

        if ($merged === null) {
            return $content;
        }

        return array_merge($content, [
            'content_html' => $merged['html'],
            'content_css' => $merged['css'],
            'content_js' => $merged['js'],
            'custom_header_scripts' => $merged['header_scripts'],
            'custom_footer_scripts' => $merged['footer_scripts'],
        ]);
    }
}
