<?php



namespace App\Modules\LandingPage\Enums;



enum LandingPageAssetType: string

{

    case Image = 'image';

    case Video = 'video';

    case Font = 'font';

    case Svg = 'svg';

    case Css = 'css';

    case Js = 'js';



    public function label(): string

    {

        return str($this->name)->headline()->toString();

    }



    public function protectedByDefault(): bool

    {

        return match ($this) {

            self::Video, self::Font => true,

            default => false,

        };

    }



    /**

     * @return list<string>

     */

    public function allowedMimeTypes(): array

    {

        return match ($this) {

            self::Image => [

                'image/jpeg',

                'image/png',

                'image/gif',

                'image/webp',

                'image/avif',

            ],

            self::Svg => ['image/svg+xml'],

            self::Video => [

                'video/mp4',

                'video/webm',

                'video/ogg',

                'video/quicktime',

            ],

            self::Font => [

                'font/woff',

                'font/woff2',

                'application/font-woff',

                'application/x-font-ttf',

                'font/ttf',

                'font/otf',

                'application/vnd.ms-fontobject',

            ],

            self::Css => ['text/css'],

            self::Js => ['application/javascript', 'text/javascript'],

        };

    }



    /**

     * @return list<string>

     */

    public function allowedExtensions(): array

    {

        return match ($this) {

            self::Image => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'],

            self::Svg => ['svg'],

            self::Video => ['mp4', 'webm', 'ogv', 'mov'],

            self::Font => ['woff', 'woff2', 'ttf', 'otf', 'eot'],

            self::Css => ['css'],

            self::Js => ['js'],

        };

    }



    public function maxSizeBytes(): int

    {

        $limits = config('landing_pages.assets.max_size_kb', []);

        $kb = (int) ($limits[$this->value] ?? $limits['default'] ?? 10240);



        return max(1, $kb) * 1024;

    }



    public function acceptAttribute(): string

    {

        return implode(',', array_map(

            fn (string $ext): string => '.'.$ext,

            $this->allowedExtensions(),

        ));

    }

}

