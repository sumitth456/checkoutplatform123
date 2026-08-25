<?php

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$page = App\Models\LandingPage::query()
    ->whereIn('name', ['Test Landing Page', 'Test Product Landing Page'])
    ->orWhere('name', 'like', '%Test%Landing%')
    ->orderByRaw("CASE WHEN name = 'Test Landing Page' THEN 0 WHEN name = 'Test Product Landing Page' THEN 1 ELSE 2 END")
    ->first();

if (! $page) {
    $names = App\Models\LandingPage::query()->pluck('name')->all();
    echo 'MISSING_PAGE'.PHP_EOL;
    echo 'AVAILABLE:'.implode('|', $names).PHP_EOL;
    exit(1);
}

echo 'SOURCE_PAGE:'.$page->name.PHP_EOL;

$service = app(App\Modules\LandingPage\Services\LandingPageTemplateService::class);

$template = App\Models\LandingPageTemplate::query()->where('name', 'Test Landing Page Template')->first();

if (! $template) {
    $template = $service->createFromPage(
        $page->fresh(['forms', 'assets']),
        'Test Landing Page Template',
        ['category' => 'General'],
        1,
    );
}

$copy = App\Models\LandingPage::query()->where('name', 'Test Landing Page Copy')->first();

if (! $copy) {
    $copy = $service->createPageFromTemplate($template, [
        'name' => 'Test Landing Page Copy',
        'campaign_id' => $page->campaign_id,
    ], 1);
}

echo 'TEMPLATE:'.$template->uuid.PHP_EOL;
echo 'COPY:'.$copy->uuid.PHP_EOL;
echo 'TEMPLATE_HTML_LEN:'.strlen((string) $template->content_html).PHP_EOL;
echo 'COPY_HTML_LEN:'.strlen((string) $copy->content_html).PHP_EOL;
echo 'FORMS_COPY:'.($copy->forms()->count()).PHP_EOL;
