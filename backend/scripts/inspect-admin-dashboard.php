<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

auth()->login(App\Models\User::where('email', 'admin@checkoutplatform.test')->first());

$request = Illuminate\Http\Request::create('/admin', 'GET');
$request->setUserResolver(fn () => auth()->user());
$response = $kernel->handle($request);
$html = $response->getContent();

echo 'status='.$response->getStatusCode().PHP_EOL;
echo 'length='.strlen($html).PHP_EOL;
echo 'has OperationsOverview: '.(str_contains($html, 'OperationsOverview') || str_contains($html, 'Today') ? 'yes' : 'no').PHP_EOL;
echo 'has wire:id: '.(preg_match('/wire:id=/', $html) ? 'yes' : 'no').PHP_EOL;
echo 'has fi-page: '.(str_contains($html, 'fi-page') ? 'yes' : 'no').PHP_EOL;
echo 'has exception: '.(str_contains($html, 'Whoops') || str_contains($html, 'Livewire') && str_contains($html, 'error') ? 'maybe' : 'no').PHP_EOL;

if (preg_match('/Operations Dashboard/', $html)) {
    echo "title present\n";
}

$kernel->terminate($request, $response);
