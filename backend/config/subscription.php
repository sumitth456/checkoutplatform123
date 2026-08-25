<?php

return [
    'charge_lock_seconds' => (int) env('SUBSCRIPTION_CHARGE_LOCK_SECONDS', 300),

    'rebill' => [
        'first_retry_days' => (int) env('SUBSCRIPTION_REBILL_FIRST_RETRY_DAYS', 7),
    ],

    'scheduler' => [
        'frequency' => env('SUBSCRIPTION_SCHEDULER_FREQUENCY', 'hourly'),
        'dunning_frequency' => env('SUBSCRIPTION_DUNNING_SCHEDULER_FREQUENCY', 'hourly'),
    ],

    'queues' => [
        'billing' => env('SUBSCRIPTION_BILLING_QUEUE', 'default'),
        'dunning' => env('SUBSCRIPTION_DUNNING_QUEUE', 'default'),
        'webhooks' => env('SUBSCRIPTION_WEBHOOK_QUEUE', 'default'),
    ],
];
