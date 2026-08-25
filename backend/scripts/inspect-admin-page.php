<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

auth()->login(App\Models\User::where('email', 'admin@checkoutplatform.test')->first());

$response = $kernel->handle(Illuminate\Http\Request::create('/admin', 'GET'));
$html = $response->getContent();

echo 'Status: '.$response->getStatusCode()."\n";
echo 'Length: '.strlen($html)."\n";
echo 'livewire.js in page: '.(preg_match('/livewire[^"\']+\.js/', $html) ? 'yes' : 'no')."\n";
echo 'wire:snapshot count: '.substr_count($html, 'wire:snapshot')."\n";
echo 'fi-sidebar: '.(str_contains($html, 'fi-sidebar') ? 'yes' : 'no')."\n";
echo 'Operations Dashboard text: '.(str_contains($html, 'Operations Dashboard') ? 'yes' : 'no')."\n";
echo 'RootTag / empty widget hints: '.(str_contains($html, 'RootTagMissing') ? 'yes' : 'no')."\n";

if (preg_match('/<main[^>]*class="([^"]*)"/', $html, $m)) {
    echo 'main class: '.$m[1]."\n";
}

if (preg_match('/<main[^>]*id="fi-main-content"[^>]*>(.*?)<\/main>/s', $html, $mainMatch)) {
    echo 'main inner length: '.strlen($mainMatch[1])."\n";
    echo 'widgets in main: '.substr_count($mainMatch[1], 'fi-wi')."\n";
    file_put_contents(__DIR__.'/../storage/app/admin-main-snippet.html', substr($mainMatch[1], 0, 8000));
}
