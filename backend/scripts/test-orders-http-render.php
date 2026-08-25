<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = App\Models\User::query()->first();
if ($user === null) {
    echo "NO_USER\n";
    exit(1);
}

auth()->login($user);

$request = Illuminate\Http\Request::create('/admin/orders', 'GET');
$response = $kernel->handle($request);
$html = $response->getContent();

echo str_contains($html, 'cp-orders-date-filters') ? "HTTP_PASS: filter class found\n" : "HTTP_FAIL: filter class missing\n";
echo str_contains($html, 'Quick range') ? "HTTP_PASS: Quick range found\n" : "HTTP_FAIL: Quick range missing\n";
echo 'status='.$response->getStatusCode()."\n";
echo 'html_length='.strlen($html)."\n";
