<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$slug = $argv[1] ?? 'test-product';
$page = App\Models\LandingPage::where('slug', $slug)->first();

if ($page === null) {
    echo "page not found: {$slug}\n";
    exit(1);
}

echo 'page='.$page->slug.' campaign_id='.($page->platform_campaign_id ?? 'null')."\n";

try {
    $context = app(App\Modules\LandingPage\Services\LandingPageCampaignContextService::class)
        ->buildCheckoutContext($page);
    echo 'context ok products='.count($context['products'] ?? [])."\n";
} catch (Throwable $e) {
    echo 'ERR: '.$e->getMessage()."\n";
    echo $e->getFile().':'.$e->getLine()."\n";
}
