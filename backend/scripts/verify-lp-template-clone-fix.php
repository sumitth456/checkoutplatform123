<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Campaign;
use App\Models\LandingPageTemplate;
use App\Modules\LandingPage\Services\LandingPageService;
use App\Modules\LandingPage\Services\LandingPageTemplateService;
use App\Modules\LandingPage\Support\LandingPageCompiledContentGuard;

$failures = 0;

// Guard tests
$legacy = '<link href="https://cdn.example/bootstrap.css" rel="stylesheet" />'.str_repeat('<section>Legacy</section>', 500);
$compiled = '<h2 class="lp-node">Your headline</h2>';
if (LandingPageCompiledContentGuard::shouldUseCompiledHtml($legacy, $compiled)) {
    echo "FAIL: guard should preserve legacy HTML\n";
    $failures++;
} else {
    echo "PASS: guard preserves legacy HTML\n";
}

// Clone test with production-like template
$template = LandingPageTemplate::query()->where('name', 'Tshirt Rooster')->first();
if ($template === null) {
    echo "SKIP: Tshirt Rooster template not found\n";
    exit($failures > 0 ? 1 : 0);
}

$campaign = Campaign::query()->first();
if ($campaign === null) {
    echo "FAIL: no campaign available for clone test\n";
    exit(1);
}

$service = app(LandingPageTemplateService::class);
$testSlug = 'template-clone-verify-'.time();

$page = $service->createPageFromTemplate($template, [
    'name' => 'Clone Verify '.date('H:i:s'),
    'slug' => $testSlug,
    'campaign_id' => $campaign->id,
]);

$templateLen = strlen((string) $template->content_html);
$pageLen = strlen((string) $page->content_html);

echo "Template html_len: {$templateLen}\n";
echo "Cloned page html_len: {$pageLen}\n";

if ($pageLen < ($templateLen * 0.9)) {
    echo "FAIL: cloned page lost template HTML\n";
    $failures++;
} else {
    echo "PASS: cloned page preserved template HTML\n";
}

app(LandingPageService::class)->publish($page->fresh());
$response = Illuminate\Support\Facades\Http::get(rtrim((string) config('app.url'), '/').'/offer/'.$testSlug);
if (! $response->successful()) {
    echo "FAIL: published page HTTP {$response->status()}\n";
    $failures++;
} elseif (! str_contains($response->body(), 'bootstrap')) {
    echo "FAIL: published page missing bootstrap markup\n";
    $failures++;
} else {
    echo "PASS: published page renders legacy HTML at /offer/{$testSlug}\n";
}

// cleanup test page
$page->delete();

exit($failures > 0 ? 1 : 0);
