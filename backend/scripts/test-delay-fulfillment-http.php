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

$request = Illuminate\Http\Request::create('/admin/delay-fulfillment', 'GET');
$request->setLaravelSession($app->make('session')->driver());
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle($request);

$html = (string) $response->getContent();
echo 'status: '.$response->getStatusCode()."\n";
echo 'len: '.strlen($html)."\n";

foreach ([
    'Search Fulfillments',
    'Scheduled',
    'Due today',
    'In transit',
    'Server Error',
    '500',
] as $needle) {
    echo $needle.': '.(str_contains($html, $needle) ? 'yes' : 'no')."\n";
}

$kernel->terminate($request, $response);
