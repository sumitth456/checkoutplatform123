<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $page->seo_title ?: $page->name }} — {{ $campaign->name }}</title>
    @if(filled($page->meta_description))
        <meta name="description" content="{{ $page->meta_description }}">
    @endif
    @if($checkoutContext)
        <script type="application/json" id="campaign-checkout-context">@json($checkoutContext)</script>
    @endif
    <script>
        window.CampaignCheckout = {
            apiUrl: @json($checkoutContext['urls']['checkout'] ?? url('/api/v1/checkout')),
            calculateUrl: @json($checkoutContext['urls']['calculate'] ?? null),
            couponValidateUrl: @json($checkoutContext['urls']['coupon_validate'] ?? url('/api/v1/coupons/validate')),
            csrfToken: @json(csrf_token()),
            campaignUuid: @json($campaign->uuid),
        };
    </script>
    @if(filled($page->custom_header_scripts))
        {!! $page->custom_header_scripts !!}
    @endif
    <style>{!! $page->content_css !!}</style>
</head>
<body data-campaign-checkout>
{!! $contentHtml !!}

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
    .lp-dynamic-product { font-family: system-ui, sans-serif; max-width: 40rem; margin: 1.5rem auto; padding: 1rem; border: 1px solid #e5e7eb; border-radius: 12px; }
    .lp-dynamic-product .lp-price { font-size: 1.5rem; font-weight: 700; margin: 0.75rem 0; }
    .lp-dynamic-product .lp-swatches { display: flex; flex-wrap: wrap; gap: 0.5rem; margin: 0.5rem 0; }
    .lp-dynamic-product .lp-swatch { padding: 0.35rem 0.75rem; border: 1px solid #d1d5db; border-radius: 999px; cursor: pointer; background: #fff; }
    .lp-dynamic-product .lp-swatch.is-active { border-color: #111; background: #111; color: #fff; }
    .lp-dynamic-product .lp-swatch.is-disabled { opacity: 0.35; pointer-events: none; }
    .lp-dynamic-product img { max-width: 100%; border-radius: 8px; }
    .lp-order-summary { max-width: 32rem; margin: 1rem auto; padding: 1rem; background: #f9fafb; border-radius: 8px; }
</style>

<script src="{{ asset('js/affiliate-attribution.js') }}"></script>
<script src="{{ asset('js/campaign-dynamic-checkout.js') }}" defer></script>
<script src="{{ asset('js/landing-page-checkout.js') }}" defer></script>
<script>{!! $page->content_js !!}</script>
@if(filled($page->custom_footer_scripts))
    {!! $page->custom_footer_scripts !!}
@endif
</body>
</html>
