<?php

namespace App\Modules\LandingPage\Builder;

final class LandingPageBuilderDocument
{
    public const VERSION = 1;

    /**
     * @return array<string, mixed>
     */
    public static function empty(): array
    {
        return [
            'version' => self::VERSION,
            'settings' => [
                'customCss' => '',
                'customJs' => '',
                'headerScripts' => '',
                'footerScripts' => '',
            ],
            'nodes' => [],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $document
     * @return array<string, mixed>
     */
    public static function normalize(?array $document): array
    {
        if ($document === null || $document === []) {
            return self::empty();
        }

        $document['version'] ??= self::VERSION;
        $document['settings'] ??= self::empty()['settings'];
        $document['nodes'] ??= [];

        return $document;
    }
}
