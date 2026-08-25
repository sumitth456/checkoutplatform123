<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$service = app(App\Modules\LandingPage\Services\LandingPageTemplateService::class);
$pages = app(App\Modules\LandingPage\Services\LandingPageService::class);

$repaired = 0;

App\Models\LandingPage::query()
    ->whereNotNull('template_id')
    ->orderBy('id')
    ->each(function (App\Models\LandingPage $page) use ($service, $pages, &$repaired): void {
        $template = App\Models\LandingPageTemplate::query()->find($page->template_id);

        if ($template === null) {
            return;
        }

        $templateHtmlLen = strlen((string) ($template->content_html ?? ''));
        $pageHtmlLen = strlen((string) ($page->content_html ?? ''));

        if ($templateHtmlLen < 2048 || $pageHtmlLen >= ($templateHtmlLen * 0.9)) {
            return;
        }

        echo "Repairing page #{$page->id} {$page->name} (html {$pageHtmlLen} -> {$templateHtmlLen})\n";

        $page->update([
            'content_html' => $template->content_html,
            'content_css' => $template->content_css,
            'content_js' => $template->content_js,
            'custom_header_scripts' => $template->custom_header_scripts,
            'custom_footer_scripts' => $template->custom_footer_scripts,
            'builder_json' => App\Modules\LandingPage\Support\LandingPageCompiledContentGuard::deepCopyDocument($template->builder_json),
        ]);

        $repaired++;
    });

echo "Repaired {$repaired} page(s).\n";
