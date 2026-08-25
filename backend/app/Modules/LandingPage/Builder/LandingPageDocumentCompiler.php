<?php

namespace App\Modules\LandingPage\Builder;

final class LandingPageDocumentCompiler
{
    /**
     * @param  array<string, mixed>  $document
     * @return array{html: string, css: string, js: string, header_scripts: string, footer_scripts: string}
     */
    public function compile(array $document): array
    {
        $document = LandingPageBuilderDocument::normalize($document);
        $cssRules = [];
        $jsBlocks = [];
        $html = $this->renderNodes($document['nodes'] ?? [], $cssRules, $jsBlocks);

        $settings = $document['settings'] ?? [];
        $css = $this->buildCss($cssRules, (string) ($settings['customCss'] ?? ''));
        $js = $this->buildJs($jsBlocks, (string) ($settings['customJs'] ?? ''));

        foreach ($document['nodes'] ?? [] as $node) {
            if (($node['type'] ?? '') === 'custom_css') {
                $css .= "\n".(string) ($node['props']['css'] ?? '');
            }
            if (($node['type'] ?? '') === 'custom_js') {
                $js .= "\n".(string) ($node['props']['js'] ?? '');
            }
        }

        return [
            'html' => $html,
            'css' => trim($css),
            'js' => trim($js),
            'header_scripts' => (string) ($settings['headerScripts'] ?? ''),
            'footer_scripts' => (string) ($settings['footerScripts'] ?? ''),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @param  list<string>  $cssRules
     * @param  list<string>  $jsBlocks
     */
    private function renderNodes(array $nodes, array &$cssRules, array &$jsBlocks): string
    {
        $html = [];

        foreach ($nodes as $node) {
            $html[] = $this->renderNode($node, $cssRules, $jsBlocks);
        }

        return implode("\n", $html);
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  list<string>  $cssRules
     * @param  list<string>  $jsBlocks
     */
    private function renderNode(array $node, array &$cssRules, array &$jsBlocks): string
    {
        $type = (string) ($node['type'] ?? 'text');
        $id = (string) ($node['id'] ?? uniqid('lp_', true));
        $props = (array) ($node['props'] ?? []);
        $styles = (array) ($node['styles'] ?? LandingPageComponentRegistry::defaultStyles());
        $children = (array) ($node['children'] ?? []);

        $cssRules[] = $this->styleRule($id, $type, $styles);
        $class = 'lp-node lp-'.e($id);

        $inner = match ($type) {
            'section', 'container' => $this->wrap('div', $class, $this->renderNodes($children, $cssRules, $jsBlocks), $type === 'section' ? 'section' : 'div'),
            'row' => $this->wrap('div', $class.' lp-row', $this->renderNodes($children, $cssRules, $jsBlocks)),
            'column' => $this->wrap('div', $class.' lp-col', $this->renderNodes($children, $cssRules, $jsBlocks)),
            'heading' => $this->wrap(
                (string) ($props['level'] ?? 'h2'),
                $class,
                e((string) ($props['text'] ?? '')),
            ),
            'text' => $this->wrap('p', $class, nl2br(e((string) ($props['text'] ?? '')))),
            'button' => sprintf(
                '<a class="%s lp-btn" href="%s" target="%s">%s</a>',
                $class,
                e((string) ($props['url'] ?? '#')),
                e((string) ($props['target'] ?? '_self')),
                e((string) ($props['text'] ?? 'Button')),
            ),
            'image' => sprintf(
                '<img class="%s" src="%s" alt="%s" loading="lazy" />',
                $class,
                e((string) ($props['src'] ?? '')),
                e((string) ($props['alt'] ?? '')),
            ),
            'video' => $this->renderVideo($class, $props),
            'divider' => sprintf('<hr class="%s lp-divider" />', $class),
            'spacer' => sprintf('<div class="%s lp-spacer" aria-hidden="true"></div>', $class),
            'icon' => sprintf('<span class="%s lp-icon" aria-hidden="true">%s</span>', $class, e((string) ($props['icon'] ?? '★'))),
            'list' => $this->renderList($class, $props),
            'countdown' => $this->renderCountdown($class, $props, $jsBlocks),
            'progress_bar' => $this->renderProgressBar($class, $props),
            'faq' => $this->renderFaq($class, $props),
            'testimonials' => $this->renderTestimonials($class, $props),
            'reviews' => $this->renderReviews($class, $props),
            'trust_badges' => $this->renderTrustBadges($class, $props),
            'guarantee_box' => sprintf(
                '<div class="%s lp-guarantee"><strong>%s</strong><p>%s</p></div>',
                $class,
                e((string) ($props['title'] ?? '')),
                e((string) ($props['text'] ?? '')),
            ),
            'html_block' => (string) ($props['html'] ?? ''),
            'custom_css', 'custom_js' => '',
            default => $this->wrap('div', $class, e((string) ($props['text'] ?? ''))),
        };

        return $inner;
    }

    /**
     * @param  array<string, mixed>  $styles
     */
    private function styleRule(string $id, string $type, array $styles): string
    {
        $rules = [];
        foreach (['desktop' => '', 'tablet' => 'max-width:1024px', 'mobile' => 'max-width:640px'] as $viewport => $media) {
            $merged = array_merge(
                LandingPageComponentRegistry::defaultStyles()['desktop'],
                (array) ($styles['desktop'] ?? []),
                (array) ($styles[$viewport] ?? []),
            );
            $css = $this->stylesToDeclaration($merged, $type);
            $selector = '.lp-'.$id;
            $block = "{$selector}{{$css}}";
            $rules[] = $media !== '' ? "@media ({$media}){{$block}}" : $block;
        }

        return implode("\n", $rules);
    }

    /**
     * @param  array<string, mixed>  $styles
     */
    private function stylesToDeclaration(array $styles, string $type): string
    {
        if (($styles['visibility'] ?? 'visible') === 'hidden') {
            return 'display:none!important;';
        }

        $map = [
            'color' => 'color',
            'backgroundColor' => 'background-color',
            'fontSize' => 'font-size',
            'fontWeight' => 'font-weight',
            'fontFamily' => 'font-family',
            'textAlign' => 'text-align',
            'padding' => 'padding',
            'margin' => 'margin',
            'width' => 'width',
            'height' => 'height',
            'border' => 'border',
            'borderRadius' => 'border-radius',
            'boxShadow' => 'box-shadow',
            'display' => 'display',
            'animation' => 'animation',
        ];

        $decl = [];
        foreach ($map as $key => $prop) {
            $value = $styles[$key] ?? null;
            if ($value !== null && $value !== '' && $value !== 'none' && $value !== 'auto') {
                $decl[] = "{$prop}:{$value}";
            }
        }

        if ($type === 'row') {
            $decl[] = 'display:flex';
            $decl[] = 'flex-wrap:wrap';
        }

        if ($type === 'column') {
            $decl[] = 'flex:1';
            $decl[] = 'min-width:0';
        }

        if ($type === 'spacer' && empty($styles['height'])) {
            $decl[] = 'height:32px';
        }

        return implode(';', $decl);
    }

    private function wrap(string $tag, string $class, string $inner, ?string $forceTag = null): string
    {
        $tag = $forceTag ?? $tag;

        return "<{$tag} class=\"{$class}\">{$inner}</{$tag}>";
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function renderVideo(string $class, array $props): string
    {
        $url = (string) ($props['url'] ?? '');
        if ($url === '') {
            return '<div class="'.$class.' lp-video-placeholder">Add video URL</div>';
        }

        if (str_contains($url, 'youtube.com') || str_contains($url, 'youtu.be')) {
            $embed = preg_replace('~(?:youtube\.com/watch\?v=|youtu\.be/)([\w-]+)~', 'https://www.youtube.com/embed/$1', $url);

            return sprintf('<iframe class="%s lp-video" src="%s" allowfullscreen loading="lazy"></iframe>', $class, e((string) $embed));
        }

        return sprintf('<video class="%s lp-video" src="%s" controls></video>', $class, e($url));
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function renderList(string $class, array $props): string
    {
        $tag = ! empty($props['ordered']) ? 'ol' : 'ul';
        $items = (array) ($props['items'] ?? []);
        $lis = collect($items)->map(fn ($item): string => '<li>'.e((string) $item).'</li>')->implode('');

        return "<{$tag} class=\"{$class} lp-list\">{$lis}</{$tag}>";
    }

    /**
     * @param  array<string, mixed>  $props
     * @param  list<string>  $jsBlocks
     */
    private function renderCountdown(string $class, array $props, array &$jsBlocks): string
    {
        $end = e((string) ($props['endAt'] ?? ''));
        $label = e((string) ($props['label'] ?? ''));
        $jsBlocks[] = "document.querySelectorAll('.{$class} [data-countdown]').forEach(function(el){var end=new Date(el.dataset.end);function tick(){var d=Math.max(0,end-new Date());var h=Math.floor(d/36e5),m=Math.floor(d/6e4)%60,s=Math.floor(d/1e3)%60;el.textContent=h+'h '+m+'m '+s+'s';}tick();setInterval(tick,1000);});";

        return sprintf(
            '<div class="%s lp-countdown"><span>%s</span> <strong data-countdown data-end="%s">--</strong></div>',
            $class,
            $label,
            $end,
        );
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function renderProgressBar(string $class, array $props): string
    {
        $value = max(0, min(100, (int) ($props['value'] ?? 0)));
        $label = e((string) ($props['label'] ?? ''));

        return sprintf(
            '<div class="%s lp-progress"><div class="lp-progress-label">%s</div><div class="lp-progress-track"><div class="lp-progress-fill" style="width:%d%%"></div></div></div>',
            $class,
            $label,
            $value,
        );
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function renderFaq(string $class, array $props): string
    {
        $items = (array) ($props['items'] ?? []);
        $html = collect($items)->map(function (array $item): string {
            return sprintf(
                '<details class="lp-faq-item"><summary>%s</summary><p>%s</p></details>',
                e((string) ($item['q'] ?? '')),
                e((string) ($item['a'] ?? '')),
            );
        })->implode('');

        return "<div class=\"{$class} lp-faq\">{$html}</div>";
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function renderTestimonials(string $class, array $props): string
    {
        $items = (array) ($props['items'] ?? []);
        $html = collect($items)->map(function (array $item): string {
            return sprintf(
                '<blockquote class="lp-testimonial"><p>“%s”</p><footer>%s — %s</footer></blockquote>',
                e((string) ($item['quote'] ?? '')),
                e((string) ($item['name'] ?? '')),
                e((string) ($item['role'] ?? '')),
            );
        })->implode('');

        return "<div class=\"{$class} lp-testimonials\">{$html}</div>";
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function renderReviews(string $class, array $props): string
    {
        $rating = (int) ($props['rating'] ?? 5);
        $stars = str_repeat('★', max(0, min(5, $rating))).str_repeat('☆', max(0, 5 - $rating));

        return sprintf(
            '<div class="%s lp-reviews"><span class="lp-stars">%s</span> <span>%s (%d)</span></div>',
            $class,
            $stars,
            e((string) ($props['label'] ?? 'Reviews')),
            (int) ($props['count'] ?? 0),
        );
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function renderTrustBadges(string $class, array $props): string
    {
        $badges = (array) ($props['badges'] ?? []);
        $html = collect($badges)->map(fn ($b): string => '<span class="lp-badge">'.e((string) $b).'</span>')->implode('');

        return "<div class=\"{$class} lp-trust-badges\">{$html}</div>";
    }

    /**
     * @param  list<string>  $cssRules
     */
    private function buildCss(array $cssRules, string $customCss): string
    {
        $base = <<<'CSS'
.lp-row{display:flex;flex-wrap:wrap;gap:16px}
.lp-col{flex:1;min-width:0}
.lp-btn{display:inline-block;text-decoration:none;cursor:pointer}
.lp-divider{border:0;border-top:1px solid #e5e7eb}
.lp-progress-track{background:#e5e7eb;border-radius:999px;height:8px;overflow:hidden}
.lp-progress-fill{background:#f59e0b;height:100%}
.lp-trust-badges{display:flex;flex-wrap:wrap;gap:8px}
.lp-badge{background:#f3f4f6;border-radius:999px;padding:4px 10px;font-size:12px}
.lp-guarantee{border:1px solid #fde68a;background:#fffbeb;border-radius:8px;padding:16px}
.lp-faq-item{border-bottom:1px solid #e5e7eb;padding:8px 0}
.lp-testimonial{background:#f9fafb;border-radius:8px;padding:16px;margin:0 0 12px}
CSS;

        return trim($base."\n".implode("\n", $cssRules)."\n".$customCss);
    }

    /**
     * @param  list<string>  $jsBlocks
     */
    private function buildJs(array $jsBlocks, string $customJs): string
    {
        if ($jsBlocks === [] && $customJs === '') {
            return '';
        }

        return "document.addEventListener('DOMContentLoaded',function(){\n"
            .implode("\n", $jsBlocks)
            ."\n".$customJs
            ."\n});";
    }
}
