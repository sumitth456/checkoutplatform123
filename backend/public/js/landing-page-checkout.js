/**
 * Binds #checkout-form on landing pages to platform checkout API.
 */
(function () {
    function generateIdempotencyKey() {
        if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
            try {
                return crypto.randomUUID();
            } catch (_) {}
        }

        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (char) => {
            const random = Math.random() * 16 | 0;
            const value = char === 'x' ? random : (random & 0x3 | 0x8);

            return value.toString(16);
        });
    }

    const form = document.getElementById('checkout-form');
    if (! form || ! window.LandingPageCheckout) {
        return;
    }

    const cfg = window.LandingPageCheckout;
    const contextEl = document.getElementById('landing-page-checkout-context');
    const context = contextEl ? JSON.parse(contextEl.textContent || '{}') : {};

    hydrateVariantSelects(form, context);

    form.addEventListener('submit', async function (event) {
        event.preventDefault();

        const fd = new FormData(form);
        const variantUuid = fd.get('variant_uuid') || fd.get('variant_id') || fd.get('product_variant');

        const payload = {
            idempotency_key: generateIdempotencyKey(),
            payment_token: fd.get('payment_token') || fd.get('stripe_token') || '',
            customer: {
                email: fd.get('email') || '',
                first_name: splitName(fd.get('customer_name')).first,
                last_name: splitName(fd.get('customer_name')).last || fd.get('last_name') || '',
                phone: fd.get('phone') || '',
            },
            shipping_address: collectAddress(fd, 'shipping'),
            billing_address: collectAddress(fd, 'billing'),
            items: [
                {
                    variant_uuid: variantUuid ? String(variantUuid) : null,
                    product_uuid: fd.get('product_uuid') || context.products?.[0]?.uuid || null,
                    campaign_product_line_id: fd.get('campaign_product_line_id') || context.products?.[0]?.line_id || null,
                    color: fd.get('color') || null,
                    size: fd.get('size') || null,
                    quantity: Number(fd.get('quantity') || 1),
                },
            ],
            coupon_code: fd.get('coupon') || fd.get('coupon_code') || null,
            landing_page: {
                id: context.landing_page_uuid || null,
                url: window.location.href,
                referrer: document.referrer || null,
            },
            metadata: window.AffiliateAttribution
                ? window.AffiliateAttribution.mergeMetadata({
                    landing_page_uuid: context.landing_page_uuid || null,
                    campaign_uuid: context.campaign_uuid || context.campaign?.uuid || cfg.campaignUuid,
                    subscription_plan_uuid: context.subscription_plan?.uuid || null,
                })
                : {
                    landing_page_uuid: context.landing_page_uuid || null,
                    campaign_uuid: context.campaign_uuid || context.campaign?.uuid || cfg.campaignUuid,
                    subscription_plan_uuid: context.subscription_plan?.uuid || null,
                },
        };

        const response = await fetch(cfg.apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': cfg.csrfToken,
            },
            body: JSON.stringify(payload),
        });

        const data = await response.json().catch(() => ({}));

        if (response.ok) {
            form.dispatchEvent(new CustomEvent('checkout:success', { detail: data }));
            return;
        }

        form.dispatchEvent(new CustomEvent('checkout:error', { detail: data }));
    });

    function hydrateVariantSelects(formEl, ctx) {
        const selects = formEl.querySelectorAll('select[data-lp-options="campaign_variants"]');
        if (! selects.length || ! ctx.products) {
            return;
        }

        const variants = [];
        ctx.products.forEach((product) => {
            (product.variants || []).forEach((variant) => {
                variants.push({
                    uuid: variant.uuid,
                    label: `${product.name || product.sku || 'Product'} — ${variant.sku || variant.name} ($${variant.price})`,
                });
            });
        });

        selects.forEach((select) => {
            variants.forEach((v) => {
                const opt = document.createElement('option');
                opt.value = v.uuid;
                opt.textContent = v.label;
                select.appendChild(opt);
            });
        });
    }

    function splitName(value) {
        const parts = String(value || '').trim().split(/\s+/);
        if (parts.length === 0) {
            return { first: '', last: '' };
        }
        if (parts.length === 1) {
            return { first: parts[0], last: '' };
        }

        return { first: parts[0], last: parts.slice(1).join(' ') };
    }

    function collectAddress(fd, prefix) {
        return {
            line1: fd.get(prefix + '_address_line1') || fd.get(prefix + '_address') || '',
            line2: fd.get(prefix + '_address_line2') || '',
            city: fd.get(prefix + '_city') || '',
            state: fd.get(prefix + '_state') || '',
            postal_code: fd.get(prefix + '_postal_code') || fd.get(prefix + '_zip') || '',
            country: fd.get(prefix + '_country') || 'US',
        };
    }
})();
