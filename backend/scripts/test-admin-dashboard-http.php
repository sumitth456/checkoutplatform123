<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = App\Models\User::query()->where('email', 'admin@checkoutplatform.test')->first()
    ?? App\Models\User::query()->first();

if ($user === null) {
    echo "User missing\n";
    exit(1);
}

Illuminate\Support\Facades\Auth::login($user);

$request = Illuminate\Http\Request::create('/admin', 'GET');
$request->setLaravelSession($app->make('session')->driver());
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle($request);

$html = (string) $response->getContent();
echo 'status: '.$response->getStatusCode()."\n";
echo 'len: '.strlen($html)."\n";

foreach ([
    'Operations Dashboard', 'Quick range', 'Key metrics', 'Total Orders', 'Sales summary', 'cp-dashboard-kpis--primary', 'cp-analytics-toolbar', 'wire:model.live="quickRange"', 'Operations breakdown', 'cp-dashboard-toolbar--page', 'dashboard-date-filters', 'operations-dashboard-page',
] as $needle) {
    echo $needle.': '.(str_contains($html, $needle) ? 'yes' : 'no')."\n";
}

$kernel->terminate($request, $response);
