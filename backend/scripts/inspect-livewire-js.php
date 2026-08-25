<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

auth()->login(App\Models\User::where('email', 'admin@checkoutplatform.test')->first());

$html = $kernel->handle(Illuminate\Http\Request::create('http://127.0.0.1:8000/admin', 'GET'))->getContent();

preg_match_all('/<script[^>]+src="([^"]*livewire[^"]+)"/', $html, $m);
foreach ($m[1] as $src) {
    echo "Script: $src\n";
    $path = parse_url($src, PHP_URL_PATH) ?: $src;
    $resp = $kernel->handle(Illuminate\Http\Request::create($path, 'GET'));
    $body = $resp->getContent();
    echo "  status={$resp->getStatusCode()} len=".strlen($body)."\n";
    $trim = ltrim($body);
    if (str_starts_with($trim, '<')) {
        echo "  WARNING: response is HTML not JS\n";
        echo '  start: '.substr($trim, 0, 80)."\n";
    } else {
        $lines = explode("\n", $body);
        echo '  line15844: '.substr($lines[15843] ?? '(missing)', 0, 100)."\n";
        // check for invalid chars
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $body)) {
            echo "  WARNING: binary/control chars in JS\n";
        }
    }
}
