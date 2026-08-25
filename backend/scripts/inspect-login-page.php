<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(Illuminate\Http\Request::create('/admin/login', 'GET'));
$html = $response->getContent();

preg_match_all('/<script[^>]+src="([^"]+)"/', $html, $scripts);
echo "Scripts:\n";
foreach ($scripts[1] as $src) {
    echo "  $src\n";
}

echo "\nwire:submit present: ".(str_contains($html, 'wire:submit') ? 'yes' : 'no')."\n";
echo "livewire.js present in html: ".(preg_match('/livewire[^"]*\.js/', $html) ? 'yes' : 'no')."\n";
echo "csrf meta: ".(preg_match('/name="csrf-token"/', $html) ? 'yes' : 'no')."\n";

$kernel->terminate(Illuminate\Http\Request::create('/admin/login', 'GET'), $response);
