<?php

return [
    'queue' => env('CHECKOUT_QUEUE', 'default'),
    'fulfillment_queue' => env('CHECKOUT_FULFILLMENT_QUEUE', 'default'),
    'demo_customer_email' => env('DEMO_CHECKOUT_EMAIL', 'sumit@test.com'),
];
