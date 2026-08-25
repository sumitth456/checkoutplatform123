<?php

return [
    'default_provider' => env('PAYMENT_DEFAULT_PROVIDER', 'stripe'),

    'sandbox_mode' => filter_var(
        env('CHECKOUT_SANDBOX_MODE', ! filled(env('STRIPE_SECRET_KEY'))),
        FILTER_VALIDATE_BOOLEAN,
    ),

    'idempotency_ttl_minutes' => (int) env('PAYMENT_IDEMPOTENCY_TTL', 1440),

    'resilience' => [
        'max_attempts' => (int) env('PAYMENT_RETRY_ATTEMPTS', 3),
        'retry_delay_ms' => (int) env('PAYMENT_RETRY_DELAY_MS', 250),
        'timeout_seconds' => (int) env('PAYMENT_TIMEOUT_SECONDS', 30),
        'circuit_failure_threshold' => (int) env('PAYMENT_CIRCUIT_FAILURE_THRESHOLD', 5),
        'circuit_recovery_seconds' => (int) env('PAYMENT_CIRCUIT_RECOVERY_SECONDS', 60),
    ],

    'providers' => [
        'stripe' => [
            'driver' => 'stripe',
            'base_url' => env('STRIPE_API_BASE', 'https://api.stripe.com/v1'),
            'secret_key' => env('STRIPE_SECRET_KEY'),
            'publishable_key' => env('STRIPE_KEY', env('STRIPE_PUBLISHABLE_KEY')),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        ],
        'paypal' => [
            'driver' => 'paypal',
            'base_url' => env('PAYPAL_API_BASE', 'https://api-m.paypal.com'),
            'client_id' => env('PAYPAL_CLIENT_ID'),
            'client_secret' => env('PAYPAL_CLIENT_SECRET'),
            'webhook_id' => env('PAYPAL_WEBHOOK_ID'),
        ],
        'authorize_net' => [
            'driver' => 'authorize_net',
            'base_url' => env('AUTHNET_API_BASE', 'https://api.authorize.net/xml/v1/request.api'),
            'api_login_id' => env('AUTHNET_API_LOGIN_ID'),
            'transaction_key' => env('AUTHNET_TRANSACTION_KEY'),
        ],
        'nmi' => [
            'driver' => 'nmi',
            'base_url' => env('NMI_API_BASE', 'https://secure.networkmerchants.com/api/transact.php'),
            'security_key' => env('NMI_SECURITY_KEY'),
        ],
    ],
];
