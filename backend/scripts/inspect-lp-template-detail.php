<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$template = App\Models\LandingPageTemplate::query()->where('name', 'Tshirt Rooster')->first()
    ?? App\Models\LandingPageTemplate::query()->orderByDesc('updated_at')->first();

if ($template === null) {
    echo "NO_TEMPLATE\n";
    exit(0);
}

echo "TEMPLATE: {$template->name}\n\n";

foreach ($template->builder_json['nodes'] ?? [] as $i => $node) {
    echo "NODE {$i}: type=".($node['type'] ?? '?').' id='.($node['id'] ?? '?')."\n";
    if (($node['type'] ?? '') === 'html_block') {
        echo '  html_len: '.strlen((string) ($node['props']['html'] ?? ''))."\n";
    }
    if (($node['type'] ?? '') === 'heading') {
        echo '  text: '.($node['props']['text'] ?? '')."\n";
    }
    echo '  children: '.count($node['children'] ?? [])."\n";
}

$html = (string) ($template->content_html ?? '');
echo "\ncontent_html preview (first 500 chars):\n".substr($html, 0, 500)."\n\n";
echo 'content_html has form: '.(str_contains($html, '<form') ? 'yes' : 'no')."\n";
echo 'content_html has checkout-form: '.(str_contains(strtolower($html), 'checkout-form') ? 'yes' : 'no')."\n";

$compiler = app(App\Modules\LandingPage\Builder\LandingPageDocumentCompiler::class);
$compiled = $compiler->compile($template->builder_json ?? []);
echo "\nRecompiled html_len: ".strlen($compiled['html'])."\n";
echo "Recompiled preview:\n".substr($compiled['html'], 0, 500)."\n";

$source = $template->source_landing_page_id
    ? App\Models\LandingPage::query()->find($template->source_landing_page_id)
    : null;

if ($source !== null) {
    echo "\nSOURCE PAGE: {$source->name}\n";
    echo 'source html_len: '.strlen((string) $source->content_html)."\n";
    echo 'source nodes: '.count($source->builder_json['nodes'] ?? [])."\n";
}
