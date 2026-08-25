<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$loginGet = Illuminate\Http\Request::create('/admin/login', 'GET');
$loginResponse = $kernel->handle($loginGet);
$sessionName = config('session.cookie');
$sessionId = null;
foreach ($loginResponse->headers->getCookies() as $cookie) {
    if ($cookie->getName() === $sessionName) {
        $sessionId = $cookie->getValue();
    }
}
preg_match('/name="csrf-token" content="([^"]+)"/', $loginResponse->getContent(), $csrfMatch);
$csrf = $csrfMatch[1] ?? '';

$post = Illuminate\Http\Request::create('/admin/session-login', 'POST', [
    'email' => 'admin@checkoutplatform.test',
    'password' => 'password',
    'remember' => '0',
    '_token' => $csrf,
]);
$post->cookies->set($sessionName, $sessionId);
$post->headers->set('Accept', 'application/json');
$post->headers->set('X-Requested-With', 'XMLHttpRequest');

$postResponse = $kernel->handle($post);
echo 'POST session-login status='.$postResponse->getStatusCode().PHP_EOL;
echo $postResponse->getContent().PHP_EOL;

$adminGet = Illuminate\Http\Request::create('/admin', 'GET');
$adminGet->cookies->set($sessionName, $sessionId);
foreach ($postResponse->headers->getCookies() as $cookie) {
    if ($cookie->getName() === $sessionName) {
        $adminGet->cookies->set($sessionName, $cookie->getValue());
    }
}
$adminResponse = $kernel->handle($adminGet);
echo 'GET /admin after login status='.$adminResponse->getStatusCode().PHP_EOL;
