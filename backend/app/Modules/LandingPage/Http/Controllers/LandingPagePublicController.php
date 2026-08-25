<?php

namespace App\Modules\LandingPage\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\LandingPage;
use App\Models\LandingPageTemplate;
use App\Modules\LandingPage\Forms\LandingPageCheckoutFormRenderer;
use App\Modules\LandingPage\Services\LandingPageAnalyticsService;
use App\Modules\LandingPage\Services\LandingPageCampaignContextService;
use App\Modules\LandingPage\Services\LandingPageFormService;
use App\Modules\LandingPage\Services\LandingPageService;
use App\Modules\LandingPage\Services\LandingPageVariableReplacer;
use App\Modules\LandingPage\Support\LandingPageBuilderPreviewDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class LandingPagePublicController extends Controller
{
    public function __construct(
        private readonly LandingPageService $pages,
        private readonly LandingPageAnalyticsService $analytics,
        private readonly LandingPageCampaignContextService $campaignContext,
        private readonly LandingPageFormService $forms,
        private readonly LandingPageCheckoutFormRenderer $formRenderer,
        private readonly LandingPageVariableReplacer $variables,
    ) {}

    public function show(Request $request, string $slug): View
    {
        $page = $this->pages->findPublishedBySlug($slug);

        abort_unless($page !== null, 404);

        $page->load('forms');

        $this->analytics->recordView($page, $request);

        $checkoutContext = null;

        try {
            $checkoutContext = $this->campaignContext->buildCheckoutContext($page);
        } catch (\Throwable $exception) {
            report($exception);
            $checkoutContext = null;
        }

        $injectedFormHtml = $this->resolveInjectedFormHtml($page);

        $renderedHtml = $checkoutContext
            ? $this->variables->replace((string) $page->content_html, $checkoutContext)
            : (string) $page->content_html;

        $renderedJs = $checkoutContext
            ? $this->variables->replace((string) $page->content_js, $checkoutContext)
            : (string) $page->content_js;

        return view('landing-pages.show', [
            'page' => $page,
            'checkoutContext' => $checkoutContext,
            'renderedHtml' => $renderedHtml,
            'renderedJs' => $renderedJs,
            'injectedFormHtml' => $injectedFormHtml,
        ]);
    }

    public function checkoutContext(string $slug): JsonResponse
    {
        $page = $this->pages->findPublishedBySlug($slug);
        abort_unless($page !== null, 404);

        return response()->json([
            'data' => $this->campaignContext->buildCheckoutContext($page),
        ]);
    }

    public function builderPreview(LandingPage $landingPage): Response
    {
        abort_unless(
            auth()->check() && auth()->user()->can('view', $landingPage),
            403,
        );

        $cacheKey = LandingPageBuilderPreviewDocument::cacheKey((string) auth()->id(), $landingPage);

        $html = Cache::get($cacheKey);

        if (! is_string($html) || $html === '') {
            $html = LandingPageBuilderPreviewDocument::fromLandingPage($landingPage);
        }

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'X-Frame-Options' => 'SAMEORIGIN',
        ]);
    }

    public function templatePreview(LandingPageTemplate $template): View
    {
        abort_unless(
            auth()->check() && auth()->user()->can('view', $template),
            403,
        );

        return view('landing-pages.template-preview', [
            'template' => $template,
            'previewHtml' => LandingPageBuilderPreviewDocument::fromTemplate($template),
            'useTemplateUrl' => \App\Filament\Admin\Resources\LandingPageTemplates\LandingPageTemplateResource::getUrl('index', [
                'use_template' => $template->uuid,
            ]),
            'builderUrl' => \App\Filament\Admin\Resources\LandingPageTemplates\LandingPageTemplateResource::getUrl('builder', [
                'record' => $template,
            ]),
        ]);
    }

    public function templateBuilderPreview(LandingPageTemplate $template): Response
    {
        abort_unless(
            auth()->check() && auth()->user()->can('view', $template),
            403,
        );

        $cacheKey = LandingPageBuilderPreviewDocument::templateCacheKey((string) auth()->id(), $template);

        $html = Cache::get($cacheKey);

        if (! is_string($html) || $html === '') {
            $html = LandingPageBuilderPreviewDocument::fromTemplate($template);
        }

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'X-Frame-Options' => 'SAMEORIGIN',
        ]);
    }

    public function preview(Request $request, LandingPage $landingPage): View
    {
        abort_unless(
            auth()->check() && auth()->user()->can('view', $landingPage),
            403,
        );

        $landingPage->load('forms');

        $checkoutContext = null;

        try {
            $checkoutContext = $this->campaignContext->buildCheckoutContext($landingPage);
        } catch (\Throwable $exception) {
            report($exception);
            $checkoutContext = null;
        }

        $renderedHtml = $checkoutContext
            ? $this->variables->replace((string) $landingPage->content_html, $checkoutContext)
            : (string) $landingPage->content_html;

        $renderedJs = $checkoutContext
            ? $this->variables->replace((string) $landingPage->content_js, $checkoutContext)
            : (string) $landingPage->content_js;

        return view('landing-pages.show', [
            'page' => $landingPage,
            'checkoutContext' => $checkoutContext,
            'renderedHtml' => $renderedHtml,
            'renderedJs' => $renderedJs,
            'injectedFormHtml' => $this->resolveInjectedFormHtml($landingPage),
            'isPreview' => true,
        ]);
    }

    private function resolveInjectedFormHtml(LandingPage $page): ?string
    {
        $form = $this->forms->activeCheckoutForm($page);

        if ($form === null) {
            return null;
        }

        $autoInject = (bool) ($form->settings['auto_inject'] ?? true);

        if (! $autoInject) {
            return null;
        }

        if ($this->formRenderer->pageContainsCheckoutForm((string) $page->content_html)) {
            return null;
        }

        return $this->formRenderer->render($form);
    }
}
