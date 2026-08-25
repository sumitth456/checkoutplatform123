<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Auth;

$email = $argv[1] ?? 'admin@checkoutplatform.test';
$password = $argv[2] ?? 'password';

// 1) Direct auth check
$user = User::query()->where('email', $email)->first();
if ($user === null) {
    echo "User not found: {$email}\n";
    exit(1);
}

$credentials = ['email' => $email, 'password' => $password];
if (! Auth::attempt($credentials)) {
    echo "Auth::attempt FAILED for {$email}\n";
    exit(1);
}
echo "Auth::attempt OK for {$email}\n";
Auth::logout();

// 2) HTTP login page
$loginGet = Illuminate\Http\Request::create('/admin/login', 'GET');
$loginGetResponse = $kernel->handle($loginGet);
echo 'GET /admin/login status='.$loginGetResponse->getStatusCode()."\n";
$kernel->terminate($loginGet, $loginGetResponse);

if ($loginGetResponse->getStatusCode() !== 200) {
    exit(1);
}

$content = $loginGetResponse->getContent();
if (! preg_match('/name="csrf-token" content="([^"]+)"/', $content, $csrfMatch)) {
    echo "Could not find CSRF token on login page\n";
    exit(1);
}
$csrf = $csrfMatch[1];

$sessionName = config('session.cookie');
$sessionId = null;
foreach ($loginGetResponse->headers->getCookies() as $cookie) {
    if ($cookie->getName() === $sessionName) {
        $sessionId = $cookie->getValue();
    }
}
if ($sessionId === null) {
    echo "No session cookie from login page (cookie name: {$sessionName})\n";
    exit(1);
}
echo "Session cookie received\n";

// 3) GET /admin while guest -> should redirect to login
$guestAdmin = Illuminate\Http\Request::create('/admin', 'GET');
$guestAdmin->cookies->set($sessionName, $sessionId);
$guestAdminResponse = $kernel->handle($guestAdmin);
echo 'GET /admin (guest) status='.$guestAdminResponse->getStatusCode()."\n";
$kernel->terminate($guestAdmin, $guestAdminResponse);

// 4) Simulate session auth and GET /admin
$session = app('session.store');
$session->setId($sessionId);
$session->start();
Auth::login($user);
$session->save();

$authedAdmin = Illuminate\Http\Request::create('/admin', 'GET');
$authedAdmin->cookies->set($sessionName, $session->getId());
$authedAdmin->setLaravelSession($session);
$t = microtime(true);
try {
    $authedResponse = $kernel->handle($authedAdmin);
    $elapsed = round(microtime(true) - $t, 2);
    echo 'GET /admin (authed) status='.$authedResponse->getStatusCode()." time={$elapsed}s\n";
    if ($authedResponse->getStatusCode() >= 500) {
        echo substr($authedResponse->getContent(), 0, 800)."\n";
    }
} catch (Throwable $e) {
    echo 'GET /admin EXCEPTION: '.$e->getMessage()."\n";
}
$kernel->terminate($authedAdmin, $authedResponse ?? null);
