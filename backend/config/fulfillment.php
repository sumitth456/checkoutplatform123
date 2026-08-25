<?php

return [
    'default_delay_days' => (int) env('FULFILLMENT_DEFAULT_DELAY_DAYS', 10),

    'queue' => env('FULFILLMENT_QUEUE', 'default'),

    'scheduler' => [
        'time' => env('FULFILLMENT_SCHEDULER_TIME', '06:00'),
        'timezone' => env('FULFILLMENT_SCHEDULER_TIMEZONE', env('APP_TIMEZONE', 'UTC')),
    ],

    'warehouse' => [
        'base_url' => env('WAREHOUSE_API_BASE_URL', 'https://warehouse.example.com/api'),
        'api_key' => env('WAREHOUSE_API_KEY', 'test_warehouse_key'),
        'timeout_seconds' => (int) env('WAREHOUSE_API_TIMEOUT', 30),
        'create_shipment_path' => env('WAREHOUSE_CREATE_SHIPMENT_PATH', '/v1/shipments'),
    ],
];
