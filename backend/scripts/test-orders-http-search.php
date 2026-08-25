<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

auth()->login(App\Models\User::query()->first());

$request = Illuminate\Http\Request::create('/admin/orders', 'GET');
$response = $kernel->handle($request);
$html = $response->getContent();

echo 'status='.$response->getStatusCode()."\n";
echo str_contains($html, 'Order Id') ? "HTTP_PASS: Order Id\n" : "HTTP_FAIL: Order Id\n";
echo str_contains($html, 'cp-orders-search-filters') ? "HTTP_PASS: search class\n" : "HTTP_FAIL: search class\n";
echo str_contains($html, 'Search Orders') ? "HTTP_PASS: Search Orders\n" : "HTTP_FAIL: Search Orders\n";

$pos = strpos($html, 'cp-orders-date-filters');
if ($pos !== false) {
    echo substr($html, $pos, 1200)."\n";
}
