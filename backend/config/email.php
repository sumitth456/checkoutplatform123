<?php

return [
    'queue' => env('EMAIL_QUEUE', env('CHECKOUT_QUEUE', 'default')),

    'process_batch_size' => (int) env('EMAIL_PROCESS_BATCH_SIZE', 50),

    'retry_backoff_minutes' => [1, 5, 15, 60, 240],

    'company_logo_url' => env('EMAIL_COMPANY_LOGO_URL'),

    'invoice_base_url' => env('EMAIL_INVOICE_BASE_URL', '/api/v1/orders'),
];
