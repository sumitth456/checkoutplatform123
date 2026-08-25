<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('checkout:place-demo-order {--email=sumit@test.com}', function (): void {
    config(['checkout.demo_customer_email' => (string) $this->option('email')]);

    $this->info('Building demo campaign, landing page, and placing checkout (simulated Stripe)...');

    $this->call('db:seed', [
        '--class' => \Database\Seeders\CheckoutChampDemoSeeder::class,
        '--force' => true,
        '--no-interaction' => true,
    ]);
})->purpose('Create demo landing page and place a test subscription order');
