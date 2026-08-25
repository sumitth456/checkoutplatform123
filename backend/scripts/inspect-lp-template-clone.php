<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$template = App\Models\LandingPageTemplate::query()->orderByDesc('updated_at')->first();

if ($template === null) {
    echo "NO_TEMPLATE\n";
    exit(0);
}

echo "TEMPLATE: {$template->name}\n";
echo 'nodes: '.count($template->builder_json['nodes'] ?? [])."\n";
echo 'html_len: '.strlen((string) ($template->content_html ?? ''))."\n";
echo 'css_len: '.strlen((string) ($template->content_css ?? ''))."\n";
echo 'js_len: '.strlen((string) ($template->content_js ?? ''))."\n";
echo 'assets_meta: '.count($template->metadata['assets'] ?? [])."\n";
echo 'forms_meta: '.count($template->metadata['forms'] ?? [])."\n";
echo 'source_page_id: '.($template->source_landing_page_id ?? 'null')."\n";

$nodeTypes = collect($template->builder_json['nodes'] ?? [])->pluck('type')->countBy()->all();
echo 'node_types: '.json_encode($nodeTypes)."\n";

$page = App\Models\LandingPage::query()
    ->where('template_id', $template->id)
    ->orderByDesc('created_at')
    ->first();

if ($page !== null) {
    echo "PAGE: {$page->name} status={$page->status->value}\n";
    echo 'page_html_len: '.strlen((string) ($page->content_html ?? ''))."\n";
    echo 'page_css_len: '.strlen((string) ($page->content_css ?? ''))."\n";
    echo 'page_js_len: '.strlen((string) ($page->content_js ?? ''))."\n";
    echo 'page_assets: '.$page->assets()->count()."\n";
    echo 'slug: '.$page->slug."\n";
    echo 'html_match: '.(($page->content_html === $template->content_html) ? 'yes' : 'no')."\n";
    echo 'css_match: '.(($page->content_css === $template->content_css) ? 'yes' : 'no')."\n";

    if ($page->content_html !== $template->content_html) {
        similar_text((string) $template->content_html, (string) $page->content_html, $pct);
        echo 'html_similarity: '.round($pct, 2)."%\n";
    }
}
