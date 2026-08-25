<?php

return [
    'endpoint' => env('STRIPE_WEBHOOK_ENDPOINT', '/api/v1/webhooks/stripe'),

    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),

    'verify_signature' => env('STRIPE_WEBHOOK_VERIFY_SIGNATURE', true),

    'tolerance_seconds' => (int) env('STRIPE_WEBHOOK_TOLERANCE_SECONDS', 300),

    'queue' => env('STRIPE_WEBHOOK_QUEUE', 'default'),

    'job' => [
        'tries' => (int) env('STRIPE_WEBHOOK_JOB_TRIES', 5),
        'backoff' => [60, 300, 900, 1800, 3600],
    ],

    'log_channel' => env('STRIPE_WEBHOOK_LOG_CHANNEL', 'stack'),
];
