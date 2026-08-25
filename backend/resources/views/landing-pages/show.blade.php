<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $page->seo_title ?: $page->name }}</title>
    @if(filled($page->meta_description))
        <meta name="description" content="{{ $page->meta_description }}">
    @endif
    @if(filled($page->og_image_path))
        <meta property="og:image" content="{{ asset($page->og_image_path) }}">
    @endif
    @if($checkoutContext)
        @php($stripePublishableKey = config('payment.providers.stripe.publishable_key'))
        @if(filled($stripePublishableKey))
            <script src="https://js.stripe.com/v3/"></script>
        @endif
        <script type="application/json" id="landing-page-checkout-context">@json($checkoutContext)</script>
    @endif
    <script>
        window.LandingPageCheckout = {
            apiUrl: @json($checkoutContext['checkout_api_url'] ?? url('/api/v1/checkout')),
            paymentIntentUrl: @json($checkoutContext['payment_intent_api_url'] ?? url('/api/v1/checkout/payment-intent')),
            calculateUrl: @json($checkoutContext['urls']['calculate'] ?? null),
            couponValidateUrl: @json($checkoutContext['urls']['coupon_validate'] ?? null),
            csrfToken: @json(csrf_token()),
            campaignUuid: @json($checkoutContext['campaign']['uuid'] ?? $checkoutContext['campaign_uuid'] ?? null),
            achTokenizeUrl: @json(url('/api/v1/ach/tokenize')),
            stripePublishableKey: @json(config('payment.providers.stripe.publishable_key')),
            allowTestCards: @json((bool) config('app.debug') || filter_var(env('CHECKOUT_ALLOW_TEST_CARDS', true), FILTER_VALIDATE_BOOLEAN)),
        };
    </script>
    @if(filled($page->custom_header_scripts))
        {!! $page->custom_header_scripts !!}
    @endif
    <style>{!! $page->content_css !!}</style>
</head>
<body>
@if(! empty($isPreview))
    <div style="background:#fef3c7;color:#92400e;padding:8px 16px;text-align:center;font-family:system-ui;font-size:14px;">
        Preview mode — not published
    </div>
@endif

{!! $renderedHtml ?? $page->content_html !!}

@if(! empty($injectedFormHtml))
    <div class="lp-checkout-form-wrapper">
        {!! $injectedFormHtml !!}
    </div>
@endif

<style>
    .lp-checkout-form { display: grid; gap: 0.75rem; max-width: 32rem; margin: 2rem auto; font-family: system-ui, sans-serif; }
    .lp-checkout-form .lp-field { display: flex; flex-direction: column; gap: 0.25rem; }
    .lp-checkout-form .lp-form-section { margin-top: 1rem; font-size: 1.1rem; }
    .lp-checkout-form input, .lp-checkout-form select, .lp-checkout-form textarea { padding: 0.5rem 0.65rem; border: 1px solid #ccc; border-radius: 6px; }
    .lp-checkout-submit { margin-top: 1rem; padding: 0.65rem 1rem; background: #111; color: #fff; border: 0; border-radius: 6px; cursor: pointer; }
</style>

<script src="{{ asset('js/affiliate-attribution.js') }}"></script>
<script src="{{ asset('js/campaign-dynamic-checkout.js') }}" defer></script>
<script src="{{ asset('js/landing-page-placeholder-fallback.js') }}"></script>
<script src="{{ asset('js/landing-page-checkout.js') }}" defer></script>
<script>{!! $renderedJs ?? $page->content_js !!}</script>
<script src="{{ asset('js/ach-checkout.js') }}?v=1"></script>
<script src="{{ asset('js/landing-page-legacy-checkout.js') }}?v=7"></script>
@if(filled($page->custom_footer_scripts))
    {!! $page->custom_footer_scripts !!}
@endif
</body>
</html>
