<?php

return [
    'public_path_prefix' => env('LANDING_PAGES_PATH_PREFIX', 'offer'),
    'checkout_api_path' => env('LANDING_PAGES_CHECKOUT_API', '/api/checkout'),
    'auto_save_debounce_seconds' => (int) env('LANDING_PAGES_AUTOSAVE_SECONDS', 30),
    'auto_save_version_interval_minutes' => (int) env('LANDING_PAGES_AUTOSAVE_VERSION_MINUTES', 5),

    'assets' => [
        'public_disk' => env('LANDING_PAGES_ASSETS_PUBLIC_DISK', 'public'),
        'protected_disk' => env('LANDING_PAGES_ASSETS_PROTECTED_DISK', 'local'),
        'signed_url_ttl_minutes' => (int) env('LANDING_PAGES_ASSETS_SIGNED_TTL', 1440),
        'max_size_kb' => [
            'default' => (int) env('LANDING_PAGES_ASSETS_MAX_KB', 10240),
            'image' => (int) env('LANDING_PAGES_ASSETS_MAX_KB_IMAGE', 5120),
            'svg' => (int) env('LANDING_PAGES_ASSETS_MAX_KB_SVG', 1024),
            'video' => (int) env('LANDING_PAGES_ASSETS_MAX_KB_VIDEO', 51200),
            'font' => (int) env('LANDING_PAGES_ASSETS_MAX_KB_FONT', 5120),
            'css' => (int) env('LANDING_PAGES_ASSETS_MAX_KB_CSS', 512),
            'js' => (int) env('LANDING_PAGES_ASSETS_MAX_KB_JS', 2048),
        ],
    ],
];
