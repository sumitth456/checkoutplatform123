/**
 * Legacy CheckoutChamp / Konnektive landing page checkout adapter.
 */
(function () {
    function generateIdempotencyKey() {
        if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
            try {
                return crypto.randomUUID();
            } catch (_) {
                // randomUUID requires a secure context (HTTPS); fall back below on HTTP.
            }
        }

        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (char) => {
            const random = Math.random() * 16 | 0;
            const value = char === 'x' ? random : (random & 0x3 | 0x8);

            return value.toString(16);
        });
    }

    function boot() {
        const contextEl = document.getElementById('landing-page-checkout-context');
        const orderForm = document.getElementById('order_form');

        if (!contextEl || !orderForm) {
            return;
        }

        let context;

        try {
            context = JSON.parse(contextEl.textContent || '{}');
        } catch {
            return;
        }

        const cfg = window.LandingPageCheckout || {};
        const catalog = context.products || [];

        const state = {
            gender: readActiveGender(),
            color: 'white',
            size: 'S',
            variantUuid: null,
            price: '0.00',
        };

        let product = resolveProductForGender(state.gender) || catalog[0] || null;

        if (!product) {
            return;
        }

        state.price = product.variants?.[0]?.price || '0.00';

        orderForm.setAttribute('novalidate', 'novalidate');

        let submitting = false;

        ensureHiddenFields();
        wireLegacySelectors();
        syncSelectionFromDom();
        patchLegacyTotals();
        bindSubmit();
        bindCompleteButton();

        function readActiveGender() {
            if (document.querySelector('#radioWomens')?.checked) {
                return 'w';
            }

            if (document.querySelector('#radioMens')?.checked) {
                return 'm';
            }

            const checked = document.querySelector('input[name="prodOpt"]:checked');

            if (checked) {
                if (checked.id === 'radioWomens') {
                    return 'w';
                }

                if (checked.id === 'radioMens') {
                    return 'm';
                }

                const label = checked.dataset?.planName
                    || document.querySelector(`label[for="${checked.id}"]`)?.textContent
                    || '';

                if (/women/i.test(label)) {
                    return 'w';
                }
            }

            return 'm';
        }

        function resolveProductForGender(gender) {
            const audience = gender === 'w' ? 'womens' : 'mens';

            return catalog.find((entry) => String(entry.audience || '').toLowerCase() === audience)
                || catalog.find((entry) => String(entry.gender || '').toLowerCase() === gender)
                || catalog.find((entry) => {
                    const name = String(entry.name || '').toLowerCase();
                    const sku = String(entry.sku || '').toUpperCase();

                    if (gender === 'w') {
                        return name.includes('women') || sku.includes('WOMENS');
                    }

                    return (name.includes("men's") || name.includes('mens') || sku.includes('MENS'))
                        && !name.includes('women');
                })
                || (gender === 'w'
                    ? catalog.find((entry) => /women/i.test(String(entry.name || '')))
                    : catalog.find((entry) => /men/i.test(String(entry.name || '')) && !/women/i.test(String(entry.name || ''))))
                || (gender === 'w' ? catalog[1] : catalog[0])
                || catalog[0]
                || null;
        }

        function readActiveSelectors(gender) {
            const isMens = gender !== 'w';
            const colorEl = document.querySelector(isMens ? '#mens-color' : '#womens-color');
            const sizeEl = document.querySelector(isMens ? '#tshirt-drop-mens' : '#tshirt-drop-womens');
            const radioEl = document.querySelector(isMens ? '#radioMens' : '#radioWomens');

            return {
                colorEl,
                sizeEl,
                radioEl,
                color: colorEl?.value || 'white',
                size: normalizeSizeLabel(sizeEl?.options[sizeEl?.selectedIndex]?.textContent || 'S'),
                option: sizeEl?.options[sizeEl?.selectedIndex] || null,
            };
        }

        function syncSelectionFromDom() {
            state.gender = readActiveGender();
            const selectors = readActiveSelectors(state.gender);

            state.color = selectors.color;
            state.size = selectors.size;

            setActiveProduct();

            const variant = findVariant(state.color, state.size);
            state.variantUuid = variant?.uuid || null;
            state.price = variant?.price || state.price;

            const hiddenVariant = orderForm.querySelector('[name="variant_uuid"]');
            const hiddenColor = orderForm.querySelector('[name="color"]');
            const hiddenSize = orderForm.querySelector('[name="size"]');
            const hiddenProduct = orderForm.querySelector('[name="product_uuid"]');
            const hiddenLine = orderForm.querySelector('[name="campaign_product_line_id"]');

            if (hiddenVariant) hiddenVariant.value = state.variantUuid || '';
            if (hiddenColor) hiddenColor.value = state.color;
            if (hiddenSize) hiddenSize.value = state.size;
            if (hiddenProduct) hiddenProduct.value = product?.uuid || '';
            if (hiddenLine) hiddenLine.value = String(product?.line_id || '');

            patchLegacyPlanIds(selectors.radioEl, variant, selectors.option);

            return variant;
        }

        function patchLegacyPlanIds(radioEl, variant, option) {
            if (!variant) {
                return;
            }

            const planName = option?.dataset?.planName
                || `${product.name} - ${capitalize(state.color)} - ${state.size}`;

            document.querySelectorAll('input[name="prodOpt"]').forEach((radio) => {
                if (!radio.checked) {
                    return;
                }

                radio.dataset.planId = variant.uuid;
                radio.dataset.planPrice = variant.price;
                radio.dataset.planName = planName;
                radio.setAttribute('data-plan-id', variant.uuid);
            });

            if (radioEl) {
                radioEl.dataset.planId = variant.uuid;
                radioEl.dataset.planPrice = variant.price;
                radioEl.dataset.planName = planName;
                radioEl.setAttribute('data-plan-id', variant.uuid);
            }

            if (option) {
                option.dataset.planId = variant.uuid;
                option.dataset.planPrice = variant.price;
            }
        }

        function setActiveProduct() {
            product = resolveProductForGender(state.gender) || product;

            const hiddenProduct = orderForm.querySelector('[name="product_uuid"]');
            const hiddenLine = orderForm.querySelector('[name="campaign_product_line_id"]');

            if (hiddenProduct) {
                hiddenProduct.value = product?.uuid || '';
            }

            if (hiddenLine) {
                hiddenLine.value = String(product?.line_id || '');
            }
        }

        function ensureHiddenFields() {
            const fields = {
                variant_uuid: '',
                product_uuid: product.uuid || '',
                campaign_product_line_id: String(product.line_id || ''),
                color: state.color,
                size: state.size,
                platform_checkout: '1',
            };

            Object.entries(fields).forEach(([name, value]) => {
                let input = orderForm.querySelector(`[name="${name}"]`);

                if (!input) {
                    input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = name;
                    orderForm.appendChild(input);
                }

                input.value = value;
            });
        }

        function wireLegacySelectors() {
            const bindings = [
                { color: '#mens-color', size: '#tshirt-drop-mens', gender: 'm', radio: '#radioMens' },
                { color: '#womens-color', size: '#tshirt-drop-womens', gender: 'w', radio: '#radioWomens' },
            ];

            bindings.forEach(({ color, size, gender, radio }) => {
                const colorEl = document.querySelector(color);
                const sizeEl = document.querySelector(size);
                const radioEl = document.querySelector(radio);

                if (!colorEl || !sizeEl) {
                    return;
                }

                const sync = () => {
                    if (radioEl && !radioEl.checked) {
                        return;
                    }

                    state.gender = gender;
                    const selectors = readActiveSelectors(gender);
                    state.color = selectors.color;
                    state.size = selectors.size;
                    setActiveProduct();
                    applyVariantSelection(selectors.option, radioEl);
                };

                colorEl.addEventListener('change', () => {
                    filterSizeOptions(sizeEl, colorEl.value);
                    sync();
                });

                sizeEl.addEventListener('change', sync);

                if (radioEl) {
                    radioEl.addEventListener('change', () => {
                        if (radioEl.checked) {
                            sync();
                        }
                    });
                }

                filterSizeOptions(sizeEl, colorEl.value);

                if (radioEl?.checked) {
                    sync();
                }
            });

            document.querySelectorAll('input[name="prodOpt"]').forEach((radio) => {
                radio.addEventListener('change', () => {
                    if (!radio.checked) {
                        return;
                    }

                    state.gender = radio.id === 'radioWomens' || /women/i.test(radio.dataset?.planName || '') ? 'w' : 'm';
                    const selectors = readActiveSelectors(state.gender);
                    state.color = selectors.color;
                    state.size = selectors.size;
                    setActiveProduct();
                    applyVariantSelection(selectors.option, radio);
                });
            });

            if (document.querySelector('#radioWomens')?.checked) {
                document.querySelector('#radioWomens')?.dispatchEvent(new Event('change'));
            } else if (document.querySelector('#radioMens')?.checked) {
                document.querySelector('#radioMens')?.dispatchEvent(new Event('change'));
            } else {
                syncSelectionFromDom();
            }
        }

        function filterSizeOptions(sizeEl, color) {
            Array.from(sizeEl.options).forEach((option) => {
                const optionColor = option.dataset.color;

                if (!optionColor) {
                    option.hidden = false;
                    return;
                }

                option.hidden = optionColor !== color;
            });

            const visible = Array.from(sizeEl.options).find((option) => !option.hidden);

            if (visible) {
                sizeEl.value = visible.value;
            }
        }

        function applyVariantSelection(option, radioEl) {
            const variant = findVariant(state.color, state.size);
            state.variantUuid = variant?.uuid || null;
            state.price = variant?.price || state.price;

            const hiddenVariant = orderForm.querySelector('[name="variant_uuid"]');
            const hiddenColor = orderForm.querySelector('[name="color"]');
            const hiddenSize = orderForm.querySelector('[name="size"]');

            if (hiddenVariant) hiddenVariant.value = state.variantUuid || '';
            if (hiddenColor) hiddenColor.value = state.color;
            if (hiddenSize) hiddenSize.value = state.size;

            if (radioEl && variant) {
                radioEl.dataset.planId = variant.uuid;
                radioEl.dataset.planPrice = variant.price;
                radioEl.dataset.planName = option?.dataset?.planName || `${product.name} - ${state.color} - ${state.size}`;
                radioEl.setAttribute('data-plan-id', variant.uuid);
            }

            if (option && variant) {
                option.dataset.planId = variant.uuid;
                option.dataset.planPrice = variant.price;
            }

            patchLegacyPlanIds(radioEl, variant, option);

            updateDisplayedTotals(option, radioEl);
            queueCalculate();
        }

        function findVariant(color, size) {
            const colorKey = capitalize(color);
            const normalizedSize = normalizeSizeLabel(size);
            const matrixUuid = product.matrix?.[colorKey]?.[normalizedSize]
                || product.matrix?.[color]?.[normalizedSize]
                || product.matrix?.[colorKey]?.[size]
                || product.matrix?.[color]?.[size];

            if (matrixUuid) {
                return (product.variants || []).find((v) => v.uuid === matrixUuid) || null;
            }

            return (product.variants || []).find((variant) => {
                const variantColor = variant.color ? String(variant.color).toLowerCase() : null;
                const variantSize = normalizeSizeLabel(String(variant.size || variant.name || ''));

                if (color && variantColor && variantColor !== String(color).toLowerCase()) {
                    return false;
                }

                return variantSize === normalizedSize;
            }) || findFallbackVariant(normalizedSize);
        }

        function findFallbackVariant(size) {
            const ladder = ['4XL', '3XL', 'XXL', 'XL', 'L', 'M', 'S'];
            const index = ladder.indexOf(size);

            if (index === -1) {
                return product.variants?.[0] || null;
            }

            for (let i = index; i < ladder.length; i++) {
                const match = (product.variants || []).find((variant) => normalizeSizeLabel(String(variant.size || variant.name || '')) === ladder[i]);

                if (match) {
                    return match;
                }
            }

            return product.variants?.[0] || null;
        }

        function updateDisplayedTotals(option, radioEl) {
            const label = option?.dataset?.planName || radioEl?.dataset?.planName || product.name;
            const price = formatMoney(state.price, context.currency);

            document.querySelectorAll('.prod-name').forEach((el) => {
                el.textContent = label;
            });

            document.querySelectorAll('.prod-price').forEach((el) => {
                el.textContent = price;
            });

            document.querySelectorAll('.total-price').forEach((el) => {
                el.textContent = price.replace('$', '');
            });

            document.querySelectorAll('[data-cf-product-price="true"]').forEach((el) => {
                el.textContent = price;
            });
        }

        function patchLegacyTotals() {
            const original = window.calculateTotal;

            window.calculateTotal = function patchedCalculateTotal() {
                if (typeof original === 'function') {
                    original();
                }

                queueCalculate();
            };
        }

        function queueCalculate() {
            if (typeof window.queueCalculate === 'function') {
                window.queueCalculate();
            }
        }

        function bindSubmit() {
            orderForm.addEventListener('submit', handleCheckout);
        }

        function bindCompleteButton() {
            const button = document.getElementById('iotfqx');

            if (!button) {
                return;
            }

            button.addEventListener('click', (event) => {
                event.preventDefault();
                handleCheckout(event);
            });
        }

        async function handleCheckout(event) {
            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }

            if (submitting) {
                return;
            }

            syncSelectionFromDom();

            const validationError = validateForm();

            if (validationError) {
                alert(validationError);
                return;
            }

            if (!state.variantUuid) {
                alert('Please select a valid product size.');
                return;
            }

            submitting = true;
            setButtonLoading(true);

            try {
                const fd = new FormData(orderForm);
                const paymentToken = await resolvePaymentToken(fd, readFormValue(orderForm, 'email'));

                if (!paymentToken) {
                    alert('Payment could not be processed. Please enter a valid test card (4242 4242 4242 4242).');
                    return;
                }

                const payload = buildPayload(fd, paymentToken);
                const response = await fetch(cfg.apiUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': cfg.csrfToken || '',
                    },
                    body: JSON.stringify(payload),
                });

                const data = await response.json().catch(() => ({}));

                if (response.ok) {
                    showOrderSuccess(data);
                    return;
                }

                const message = data?.message
                    || (data?.errors ? Object.values(data.errors).flat().join('\n') : null)
                    || 'Checkout failed. Please verify your details and try again.';
                alert(message);
            } catch (error) {
                const detail = error instanceof Error ? error.message : String(error);
                alert(`Something went wrong while placing your order. Please try again.${detail ? `\n\n(${detail})` : ''}`);
                console.error(error);
            } finally {
                submitting = false;
                setButtonLoading(false);
            }
        }

        function validateForm() {
            const email = readFormValue(orderForm, 'email');
            const firstName = readFormValue(orderForm, 'firstName');
            const lastName = readFormValue(orderForm, 'lastName');
            const address = readFormValue(orderForm, 'address1');
            const city = readFormValue(orderForm, 'city');
            const zip = readFormValue(orderForm, 'zip');
            const cardNumber = readCardNumber(orderForm);
            const achSelected = window.AchCheckout?.isAchSelected?.() === true;

            if (!firstName) return 'Please enter your first name.';
            if (!lastName) return 'Please enter your last name.';
            if (!email || !email.includes('@')) return 'Please enter a valid email address.';
            if (!address) return 'Please enter your shipping address.';
            if (!city) return 'Please enter your city.';
            if (!zip) return 'Please enter your zip code.';

            if (achSelected) {
                return window.AchCheckout?.validateAchFields?.(orderForm) || null;
            }

            if (cardNumber.length < 13) return 'Please enter your card number.';

            return null;
        }

        function buildPayload(fd, paymentToken) {
            syncSelectionFromDom();

            const items = [{
                variant_uuid: state.variantUuid,
                product_uuid: product.uuid,
                campaign_product_line_id: product.line_id,
                color: state.color,
                size: state.size,
                quantity: 1,
            }];

            if (document.getElementById('journeyProd')?.checked) {
                const journeyId = document.getElementById('journeyProd')?.value;

                if (journeyId) {
                    items.push({ order_bump_uuid: journeyId, quantity: 1 });
                }
            }

            if (document.getElementById('bump_offer_4684044')?.checked) {
                const agedId = document.getElementById('bump_offer_4684044')?.value;

                if (agedId) {
                    items.push({ order_bump_uuid: agedId, quantity: 1 });
                }
            }

            return {
                idempotency_key: generateIdempotencyKey(),
                payment_token: paymentToken,
                payment_method: window.AchCheckout?.isAchSelected?.() ? 'ach' : 'card',
                customer: {
                    email: String(fd.get('email') || readFormValue(orderForm, 'email') || ''),
                    first_name: String(fd.get('firstName') || readFormValue(orderForm, 'firstName') || ''),
                    last_name: String(fd.get('lastName') || readFormValue(orderForm, 'lastName') || ''),
                    phone: String(fd.get('phone') || readFormValue(orderForm, 'phone') || ''),
                },
                shipping_address: {
                    line1: String(fd.get('address1') || readFormValue(orderForm, 'address1') || ''),
                    city: String(fd.get('city') || readFormValue(orderForm, 'city') || ''),
                    state: String(fd.get('state') || readFormValue(orderForm, 'state') || ''),
                    postal_code: String(fd.get('zip') || readFormValue(orderForm, 'zip') || ''),
                    country: String(fd.get('country') || 'US'),
                },
                billing_address: {
                    line1: String(fd.get('address1') || readFormValue(orderForm, 'address1') || ''),
                    city: String(fd.get('city') || readFormValue(orderForm, 'city') || ''),
                    state: String(fd.get('state') || readFormValue(orderForm, 'state') || ''),
                    postal_code: String(fd.get('zip') || readFormValue(orderForm, 'zip') || ''),
                    country: String(fd.get('billCountry') || 'US'),
                },
                items,
                metadata: window.AffiliateAttribution
                    ? window.AffiliateAttribution.mergeMetadata({
                        campaign_uuid: cfg.campaignUuid || context.campaign?.uuid || null,
                        landing_page_uuid: context.landing_page_uuid || null,
                        subscription_plan_uuid: context.subscription_plan?.uuid || null,
                        legacy_form: 'order_form',
                        audience: state.gender === 'w' ? 'womens' : 'mens',
                        gender: state.gender,
                    })
                    : {
                        campaign_uuid: cfg.campaignUuid || context.campaign?.uuid || null,
                        landing_page_uuid: context.landing_page_uuid || null,
                        subscription_plan_uuid: context.subscription_plan?.uuid || null,
                        legacy_form: 'order_form',
                        audience: state.gender === 'w' ? 'womens' : 'mens',
                        gender: state.gender,
                    },
                landing_page: {
                    id: context.landing_page_uuid || null,
                    url: window.location.href,
                    referrer: document.referrer || null,
                },
            };
        }

        function showOrderSuccess(data) {
            const order = data?.data?.order || data?.order || data?.data || {};
            const orderNumber = order.order_number || order.uuid || 'confirmed';
            const total = order.grand_total || state.price;

            let banner = document.getElementById('lp-order-success');

            if (!banner) {
                banner = document.createElement('div');
                banner.id = 'lp-order-success';
                banner.style.cssText = 'position:fixed;top:0;left:0;right:0;background:#15803d;color:#fff;padding:18px 16px;text-align:center;font-size:18px;font-weight:600;z-index:999999;font-family:system-ui,sans-serif;box-shadow:0 4px 12px rgba(0,0,0,.2);';
                document.body.prepend(banner);
            }

            banner.innerHTML = `✓ Your order has been placed successfully!<br><span style="font-size:15px;font-weight:500;">Order #${escapeHtml(String(orderNumber))} — Total ${escapeHtml(formatMoney(total, context.currency))}</span>`;

            orderForm.querySelectorAll('button, input, select, textarea').forEach((el) => {
                el.disabled = true;
            });

            window.scrollTo({ top: 0, behavior: 'smooth' });
            alert(`Your order has been placed successfully!\n\nOrder #${orderNumber}\nTotal: ${formatMoney(total, context.currency)}`);
        }

        function setButtonLoading(loading) {
            const button = document.getElementById('iotfqx');

            if (!button) {
                return;
            }

            if (loading) {
                button.dataset.originalText = button.textContent || 'Complete Order';
                button.textContent = 'Processing order...';
                button.disabled = true;
                return;
            }

            button.textContent = button.dataset.originalText || 'Complete Order';
            button.disabled = false;
        }

        async function resolvePaymentToken(fd, customerEmail) {
            if (window.AchCheckout?.isAchSelected?.()) {
                const token = await window.AchCheckout.tokenize(orderForm, customerEmail);

                if (!token) {
                    throw new Error('Unable to verify bank account.');
                }

                return token;
            }

            const existing = fd.get('payment_token') || fd.get('stripe_token');

            if (existing) {
                return String(existing);
            }

            const cardNumber = readCardNumber(orderForm);

            if (cfg.allowTestCards !== false) {
                const testToken = resolveStripeTestToken(cardNumber);

                if (testToken) {
                    return testToken;
                }
            }

            if (window.Stripe && cfg.stripePublishableKey && cardNumber) {
                const stripe = window.Stripe(cfg.stripePublishableKey);
                const result = await stripe.createToken('card', {
                    number: cardNumber,
                    exp_month: String(readFormValue(orderForm, 'cardMonth') || ''),
                    exp_year: String(readFormValue(orderForm, 'cardYear') || ''),
                    cvc: String(readFormValue(orderForm, 'cardSecurityCode') || ''),
                });

                if (result.token?.id) {
                    return result.token.id;
                }
            }

            if (cardNumber.length >= 13 && cfg.allowTestCards !== false) {
                return 'pm_card_visa';
            }

            return null;
        }

        function readFormValue(formEl, name) {
            const fields = formEl.querySelectorAll(`[name="${name}"]`);

            for (const field of fields) {
                const value = field.value || field.textContent;

                if (value) {
                    return String(value).trim();
                }
            }

            return fdSafeGet(formEl, name);
        }

        function fdSafeGet(formEl, name) {
            try {
                const value = new FormData(formEl).get(name);
                return value ? String(value).trim() : '';
            } catch {
                return '';
            }
        }

        function readCardNumber(formEl) {
            const fields = formEl.querySelectorAll('[name="cardNumber"]');
            let digits = '';

            fields.forEach((field) => {
                const value = String(field.value || '').replace(/\D/g, '');

                if (value.length > digits.length) {
                    digits = value;
                }
            });

            return digits;
        }

        function resolveStripeTestToken(cardNumber) {
            const testCards = {
                '4242424242424242': 'pm_card_visa',
                '4242424242424241': 'pm_card_visa',
                '4111111111111111': 'pm_card_visa',
                '4000000000000002': 'pm_card_chargeDeclined',
                '4000000000009995': 'pm_card_chargeDeclinedInsufficientFunds',
                '5555555555554444': 'pm_card_mastercard',
            };

            return testCards[cardNumber] || null;
        }

        function normalizeSizeLabel(size) {
            const normalized = String(size || '').trim().toLowerCase();

            switch (normalized) {
                case 's':
                case 'sm':
                case 'small':
                    return 'S';
                case 'm':
                case 'md':
                case 'medium':
                    return 'M';
                case 'l':
                case 'lg':
                case 'large':
                    return 'L';
                case 'xl':
                    return 'XL';
                case 'xxl':
                case '2xl':
                case '2x':
                    return 'XXL';
                case 'xxxl':
                case '3xl':
                case '3x':
                    return '3XL';
                case 'xxxxl':
                case '4xl':
                case '4x':
                    return '4XL';
                default:
                    return String(size || '').trim().toUpperCase();
            }
        }

        function capitalize(value) {
            return String(value || '').charAt(0).toUpperCase() + String(value || '').slice(1).toLowerCase();
        }

        function formatMoney(value, currency) {
            const amount = Number(value || 0);

            try {
                return new Intl.NumberFormat(undefined, { style: 'currency', currency: currency || 'USD' }).format(amount);
            } catch {
                return `$${amount.toFixed(2)}`;
            }
        }

        function escapeHtml(str) {
            return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
