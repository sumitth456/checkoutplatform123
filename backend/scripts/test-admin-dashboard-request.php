<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$user = App\Models\User::query()->where('email', 'builder@checkoutplatform.test')->first();
if ($user === null) {
    echo "User missing\n";
    exit(1);
}

$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

Illuminate\Support\Facades\Auth::login($user);

$request = Illuminate\Http\Request::create('/admin', 'GET');
$request->setLaravelSession($app->make('session')->driver());
$response = $kernel->handle($request);

echo 'GET /admin status: '.$response->getStatusCode()."\n";
if ($response->getStatusCode() >= 400) {
    echo substr($response->getContent(), 0, 1500)."\n";
}

$kernel->terminate($request, $response);
