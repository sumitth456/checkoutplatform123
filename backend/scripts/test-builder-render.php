<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = App\Models\User::query()->first();
if (! $user) {
    echo "No user\n";
    exit(1);
}

auth()->login($user);

$page = App\Models\LandingPage::query()->first();
if (! $page) {
    echo "No landing page\n";
    exit(1);
}

$html = Livewire\Livewire::test(
    App\Filament\Admin\Resources\LandingPages\Pages\BuildLandingPage::class,
    ['record' => $page->getRouteKey()],
)->html();

echo "--- Livewire HTML start ---\n";
echo substr(ltrim($html), 0, 300)."\n";
echo str_contains($html, 'fi-landing-page-builder') ? "OK: builder markup present\n" : "FAIL: builder markup missing\n";
echo str_contains($html, 'landing-page-builder.js') ? "OK: builder js linked\n" : "FAIL: builder js missing\n";
echo str_contains($html, 'monaco-editor') ? "OK: monaco linked\n" : "FAIL: monaco missing\n";

$url = App\Filament\Admin\Resources\LandingPages\LandingPageResource::getUrl('builder', ['record' => $page]);
$response = Illuminate\Support\Facades\Route::dispatch(
    Illuminate\Http\Request::create($url, 'GET')
);
$full = $response->getContent();
$posBuilder = strpos($full, 'landing-page-builder.js');
$posFilament = strpos($full, '/livewire/livewire.js') ?: strpos($full, 'alpine');
echo "--- Full HTTP ---\n";
echo 'Status: '.$response->getStatusCode()."\n";
echo str_contains($full, 'fi-landing-page-builder') ? "OK: builder markup present\n" : "FAIL: builder markup missing\n";
echo str_contains($full, 'landing-page-builder.js') ? "OK: builder js linked\n" : "FAIL: builder js missing\n";
if ($posBuilder !== false && $posFilament !== false) {
    echo ($posBuilder < $posFilament ? "OK: builder js before livewire/alpine\n" : "WARN: builder js after livewire/alpine\n");
}
