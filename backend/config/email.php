<?php

return [
    'queue' => env('EMAIL_QUEUE', env('CHECKOUT_QUEUE', 'default')),

    'process_batch_size' => (int) env('EMAIL_PROCESS_BATCH_SIZE', 50),

    'retry_backoff_minutes' => [1, 5, 15, 60, 240],

    'company_logo_url' => env('EMAIL_COMPANY_LOGO_URL'),

    'invoice_base_url' => env('EMAIL_INVOICE_BASE_URL', '/api/v1/orders'),

    'mailjet' => [
        'api_key' => env('MAILJET_API_KEY'),
        'secret_key' => env('MAILJET_SECRET_KEY'),
        'from_address' => env('MAIL_FROM_ADDRESS'),
        'from_name' => env('MAIL_FROM_NAME', env('APP_NAME')),
        'reply_to' => env('MAIL_REPLY_TO'),
        'default_provider' => filter_var(env('MAILJET_DEFAULT_PROVIDER', true), FILTER_VALIDATE_BOOLEAN),
    ],
];
