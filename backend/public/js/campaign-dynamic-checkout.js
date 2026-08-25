/**
 * Dynamic campaign checkout — product matrix, live totals, inventory.
 */
(function () {
    const contextEl = document.getElementById('campaign-checkout-context')
        || document.getElementById('landing-page-checkout-context');

    if (!contextEl) {
        return;
    }

    let context;

    try {
        context = JSON.parse(contextEl.textContent || '{}');
    } catch {
        return;
    }

    const cfg = window.CampaignCheckout || window.LandingPageCheckout || {};
    if (!cfg.calculateUrl && context.urls?.calculate) {
        cfg.calculateUrl = context.urls.calculate;
    }
    const product = (context.products || [])[0];

    if (!product) {
        return;
    }

    mountDynamicProductSections(product);
    bindLiveTotals();

    function mountDynamicProductSections(activeProduct) {
        document.querySelectorAll('[data-lp-dynamic-product]').forEach((root) => {
            if (root.dataset.lpMounted === '1') {
                return;
            }

            root.dataset.lpMounted = '1';

            const state = {
                productUuid: activeProduct.uuid,
                lineId: activeProduct.line_id,
                color: activeProduct.colors?.[0] || null,
                size: null,
                quantity: 1,
                variant: null,
            };

            root.innerHTML = `
                <div class="lp-dynamic-product">
                    <div data-lp-image></div>
                    <h3 data-lp-name>${escapeHtml(activeProduct.name || '')}</h3>
                    <div class="lp-price" data-lp-price>—</div>
                    <div data-lp-colors-wrap></div>
                    <div data-lp-sizes-wrap></div>
                    <input type="hidden" name="product_uuid" value="${escapeAttr(activeProduct.uuid)}" />
                    <input type="hidden" name="campaign_product_line_id" value="${activeProduct.line_id || ''}" />
                    <input type="hidden" name="variant_uuid" data-lp-variant-input value="" />
                    <input type="hidden" name="color" data-lp-color-input value="" />
                    <input type="hidden" name="size" data-lp-size-input value="" />
                </div>
            `;

            renderColorSwatches(root, activeProduct, state);
            syncSelection(root, activeProduct, state);
        });
    }

    function renderColorSwatches(root, activeProduct, state) {
        const wrap = root.querySelector('[data-lp-colors-wrap]');

        if (!wrap || !(activeProduct.colors || []).length) {
            return;
        }

        wrap.innerHTML = `<label>Color</label><div class="lp-swatches">${activeProduct.colors.map((color) =>
            `<button type="button" class="lp-swatch" data-lp-color="${escapeAttr(color)}">${escapeHtml(color)}</button>`,
        ).join('')}</div>`;

        wrap.querySelectorAll('[data-lp-color]').forEach((btn) => {
            btn.addEventListener('click', () => {
                state.color = btn.dataset.lpColor;
                state.size = null;
                renderSizeSwatches(root, activeProduct, state);
                syncSelection(root, activeProduct, state);
                queueCalculate();
            });
        });

        renderSizeSwatches(root, activeProduct, state);
    }

    function renderSizeSwatches(root, activeProduct, state) {
        const wrap = root.querySelector('[data-lp-sizes-wrap]');

        if (!wrap) {
            return;
        }

        const sizes = availableSizes(activeProduct, state.color);

        if (!sizes.length) {
            wrap.innerHTML = '';
            return;
        }

        wrap.innerHTML = `<label>Size</label><div class="lp-swatches">${sizes.map((size) => {
            const variant = findVariant(activeProduct, state.color, size);
            const disabled = variant && variant.in_stock === false;

            return `<button type="button" class="lp-swatch${disabled ? ' is-disabled' : ''}" data-lp-size="${escapeAttr(size)}">${escapeHtml(size)}</button>`;
        }).join('')}</div>`;

        wrap.querySelectorAll('[data-lp-size]').forEach((btn) => {
            if (btn.classList.contains('is-disabled')) {
                return;
            }

            btn.addEventListener('click', () => {
                state.size = btn.dataset.lpSize;
                syncSelection(root, activeProduct, state);
                queueCalculate();
            });
        });
    }

    function availableSizes(activeProduct, color) {
        if (!color || !activeProduct.matrix?.[color]) {
            return activeProduct.sizes || [];
        }

        return Object.keys(activeProduct.matrix[color]);
    }

    function findVariant(activeProduct, color, size) {
        const uuid = color && size ? activeProduct.matrix?.[color]?.[size] : null;

        if (uuid) {
            return (activeProduct.variants || []).find((v) => v.uuid === uuid) || null;
        }

        return (activeProduct.variants || []).find((v) => {
            if (color && v.color !== color) return false;
            if (size && v.size !== size) return false;
            return true;
        }) || null;
    }

    function syncSelection(root, activeProduct, state) {
        const variant = findVariant(activeProduct, state.color, state.size);
        state.variant = variant;

        root.querySelectorAll('[data-lp-color]').forEach((btn) => {
            btn.classList.toggle('is-active', btn.dataset.lpColor === state.color);
        });

        root.querySelectorAll('[data-lp-size]').forEach((btn) => {
            btn.classList.toggle('is-active', btn.dataset.lpSize === state.size);
        });

        const priceEl = root.querySelector('[data-lp-price]');
        const imageEl = root.querySelector('[data-lp-image]');
        const variantInput = root.querySelector('[data-lp-variant-input]');
        const colorInput = root.querySelector('[data-lp-color-input]');
        const sizeInput = root.querySelector('[data-lp-size-input]');

        if (variant) {
            if (priceEl) priceEl.textContent = formatMoney(variant.price, context.currency);
            if (imageEl) {
                const src = (variant.images || [])[0] || (activeProduct.images || [])[0] || '';
                imageEl.innerHTML = src ? `<img src="${escapeAttr(src)}" alt="" />` : '';
            }
            if (variantInput) variantInput.value = variant.uuid;
            if (colorInput) colorInput.value = state.color || '';
            if (sizeInput) sizeInput.value = state.size || '';
        } else if (state.color && !state.size) {
            if (priceEl) priceEl.textContent = 'Select a size';
        }

        document.dispatchEvent(new CustomEvent('campaign:selection-changed', { detail: { variant, state } }));
    }

    function bindLiveTotals() {
        let timer;

        const run = () => {
            clearTimeout(timer);
            timer = setTimeout(calculateTotals, 350);
        };

        window.queueCalculate = run;

        document.addEventListener('campaign:selection-changed', run);
        document.querySelectorAll('#checkout-form input, #checkout-form select, #order_form input, #order_form select').forEach((el) => {
            el.addEventListener('change', run);
        });

        run();
    }

    async function calculateTotals() {
        if (!cfg.calculateUrl) {
            return;
        }

        const form = document.getElementById('checkout-form') || document.getElementById('order_form');
        const variantUuid = document.querySelector('[data-lp-variant-input]')?.value
            || document.querySelector('[name="variant_uuid"]')?.value
            || form?.querySelector('[name="variant_uuid"]')?.value;

        if (!variantUuid) {
            return;
        }

        const fd = form ? new FormData(form) : new FormData();
        const quantity = Number(fd.get('quantity') || 1);

        const payload = {
            items: [{
                variant_uuid: variantUuid,
                product_uuid: product.uuid,
                campaign_product_line_id: product.line_id,
                color: fd.get('color') || document.querySelector('[data-lp-color-input]')?.value || null,
                size: fd.get('size') || document.querySelector('[data-lp-size-input]')?.value || null,
                quantity,
            }],
            coupon_code: fd.get('coupon') || fd.get('coupon_code') || null,
            shipping_address: {
                country: fd.get('shipping_country') || 'US',
            },
        };

        const response = await fetch(cfg.calculateUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': cfg.csrfToken || '',
            },
            body: JSON.stringify(payload),
        });

        const json = await response.json().catch(() => ({}));

        if (!response.ok) {
            return;
        }

        renderSummary(json.data || {});
        replaceVariableTokens(json.data || {});
    }

    function renderSummary(data) {
        let summary = document.getElementById('lp-order-summary');

        if (!summary) {
            summary = document.createElement('div');
            summary.id = 'lp-order-summary';
            summary.className = 'lp-order-summary';
            document.body.appendChild(summary);
        }

        summary.innerHTML = `
            <div>Subtotal: ${formatMoney(data.subtotal, data.currency)}</div>
            <div>Shipping: ${formatMoney(data.shipping_total, data.currency)}</div>
            <div>Tax: ${formatMoney(data.tax_total, data.currency)}</div>
            <div><strong>Total: ${formatMoney(data.grand_total, data.currency)}</strong></div>
        `;
    }

    function replaceVariableTokens(data) {
        document.querySelectorAll('[data-lp-var="order.total"]').forEach((el) => {
            el.textContent = formatMoney(data.grand_total, data.currency);
        });

        document.querySelectorAll('[data-lp-var="product.price"]').forEach((el) => {
            el.textContent = formatMoney(data.items?.[0]?.unit_price, data.currency);
        });
    }

    function formatMoney(value, currency) {
        if (value === null || value === undefined || value === '') {
            return '—';
        }

        const amount = Number(value);

        try {
            return new Intl.NumberFormat(undefined, { style: 'currency', currency: currency || 'USD' }).format(amount);
        } catch {
            return `$${amount.toFixed(2)}`;
        }
    }

    function escapeHtml(str) {
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function escapeAttr(str) {
        return escapeHtml(str).replace(/'/g, '&#39;');
    }
})();
