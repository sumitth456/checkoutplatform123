<?php

return [
    'enabled' => env('ACH_ENABLED', true),
    'sandbox_mode' => env('ACH_SANDBOX_MODE', true),
    'default_provider' => env('ACH_DEFAULT_PROVIDER', 'sandbox_ach'),
    'rate_limit' => [
        'tokenize' => env('ACH_TOKENIZE_RATE_LIMIT', '10,1'),
    ],
    'fail_test_routing' => env('ACH_FAIL_TEST_ROUTING', '000000000'),
    'providers' => [
        'sandbox_ach' => [
            'base_url' => null,
        ],
        'stripe_ach' => [
            'base_url' => 'https://api.stripe.com/v1',
        ],
        'authorize_net_echeck' => [
            'base_url' => 'https://api.authorize.net/xml/v1/request.api',
        ],
        'nmi_ach' => [
            'base_url' => 'https://secure.nmi.com/api/transact.php',
        ],
        'usaepay_ach' => [
            'base_url' => 'https://www.usaepay.com/api/v2',
        ],
    ],
];
