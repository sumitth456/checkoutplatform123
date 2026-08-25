/**
 * ACH bank transfer checkout helper.
 * Tokenizes bank details server-side; never sends raw account numbers to checkout.
 */
(function () {
    const PAYMENT_CARD = 'card';
    const PAYMENT_ACH = 'ach';

    const state = {
        paymentMethod: PAYMENT_CARD,
        tokenizing: false,
    };

    function cfg() {
        return window.LandingPageCheckout || {};
    }

    function tokenizeUrl() {
        return cfg().achTokenizeUrl || '/api/v1/ach/tokenize';
    }

    function normalizeDigits(value) {
        return String(value || '').replace(/\D/g, '');
    }

    function getAchFields(root) {
        const form = root || document;

        return {
            accountHolderName: form.querySelector('[name="ach_account_holder_name"], #ach_account_holder_name'),
            bankName: form.querySelector('[name="ach_bank_name"], #ach_bank_name'),
            routingNumber: form.querySelector('[name="ach_routing_number"], #ach_routing_number'),
            accountNumber: form.querySelector('[name="ach_account_number"], #ach_account_number'),
            accountNumberConfirmation: form.querySelector('[name="ach_account_number_confirmation"], #ach_account_number_confirmation'),
            accountType: form.querySelector('[name="ach_account_type"], #ach_account_type'),
            accountHolderType: form.querySelector('[name="ach_account_holder_type"], #ach_account_holder_type'),
        };
    }

    function readField(field) {
        if (!field) {
            return '';
        }

        return String(field.value || '').trim();
    }

    function validateAchFields(root) {
        const fields = getAchFields(root);
        const routing = normalizeDigits(readField(fields.routingNumber));
        const account = normalizeDigits(readField(fields.accountNumber));
        const confirm = normalizeDigits(readField(fields.accountNumberConfirmation));
        const holder = readField(fields.accountHolderName);
        const accountType = readField(fields.accountType);

        if (!holder) {
            return 'Account holder name is required.';
        }

        if (!/^\d{9}$/.test(routing)) {
            return 'Routing number must be exactly 9 digits.';
        }

        if (!/^\d{4,17}$/.test(account)) {
            return 'Account number must be between 4 and 17 digits.';
        }

        if (account !== confirm) {
            return 'Account numbers do not match.';
        }

        if (!accountType) {
            return 'Please select an account type.';
        }

        return null;
    }

    function collectAchPayload(root, customerEmail) {
        const fields = getAchFields(root);

        return {
            account_holder_name: readField(fields.accountHolderName),
            bank_name: readField(fields.bankName) || null,
            routing_number: normalizeDigits(readField(fields.routingNumber)),
            account_number: normalizeDigits(readField(fields.accountNumber)),
            account_number_confirmation: normalizeDigits(readField(fields.accountNumberConfirmation)),
            account_type: readField(fields.accountType),
            account_holder_type: readField(fields.accountHolderType) || 'personal',
            customer_email: customerEmail || null,
        };
    }

    async function tokenize(root, customerEmail) {
        const validationError = validateAchFields(root);

        if (validationError) {
            throw new Error(validationError);
        }

        if (state.tokenizing) {
            throw new Error('Bank account verification is already in progress.');
        }

        state.tokenizing = true;
        setAchLoading(true);

        try {
            const response = await fetch(tokenizeUrl(), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': cfg().csrfToken || '',
                },
                body: JSON.stringify(collectAchPayload(root, customerEmail)),
            });

            const data = await response.json().catch(() => ({}));

            if (!response.ok) {
                const message = data?.message
                    || (data?.errors ? Object.values(data.errors).flat().join('\n') : null)
                    || 'Unable to verify bank account. Please check your details.';
                throw new Error(message);
            }

            return data?.data?.payment_token || data?.payment_token || null;
        } finally {
            state.tokenizing = false;
            setAchLoading(false);
        }
    }

    function setAchLoading(loading) {
        const panel = document.getElementById('ach-payment-panel');

        if (!panel) {
            return;
        }

        panel.querySelectorAll('input, select, button').forEach((el) => {
            if (el.dataset.achTab === 'true') {
                return;
            }

            el.disabled = loading;
        });

        const status = document.getElementById('ach-loading-status');

        if (status) {
            status.hidden = !loading;
        }
    }

    function ensurePaymentMethodTabs(anchorEl) {
        if (document.getElementById('cp-payment-method-tabs')) {
            return;
        }

        const host = anchorEl?.closest('form') || document.getElementById('order_form') || document.body;
        const cardSection = findCardSection(host);

        const wrapper = document.createElement('div');
        wrapper.id = 'cp-payment-method-tabs';
        wrapper.className = 'cp-payment-method-tabs';
        wrapper.innerHTML = `
            <div class="cp-payment-tabs" role="tablist" aria-label="Payment method">
                <button type="button" class="cp-payment-tab cp-payment-tab--active" data-method="${PAYMENT_CARD}" data-ach-tab="true" aria-selected="true">
                    <span class="cp-payment-tab__icon" aria-hidden="true">💳</span>
                    Credit / Debit Card
                </button>
                <button type="button" class="cp-payment-tab" data-method="${PAYMENT_ACH}" data-ach-tab="true" aria-selected="false">
                    <span class="cp-payment-tab__icon" aria-hidden="true">🏦</span>
                    ACH Bank Transfer
                </button>
            </div>
            <div id="ach-payment-panel" class="cp-ach-panel" hidden>
                <p class="cp-ach-panel__lead">Pay securely from your US bank account. Funds typically settle in 3–5 business days.</p>
                <div class="cp-ach-grid">
                    <label class="cp-ach-field cp-ach-field--full">
                        <span>Account holder name *</span>
                        <input type="text" id="ach_account_holder_name" name="ach_account_holder_name" autocomplete="name" maxlength="255">
                    </label>
                    <label class="cp-ach-field cp-ach-field--full">
                        <span>Bank name (optional)</span>
                        <input type="text" id="ach_bank_name" name="ach_bank_name" maxlength="255">
                    </label>
                    <label class="cp-ach-field">
                        <span>Routing number *</span>
                        <input type="text" inputmode="numeric" id="ach_routing_number" name="ach_routing_number" maxlength="9" autocomplete="off">
                    </label>
                    <label class="cp-ach-field">
                        <span>Account type *</span>
                        <select id="ach_account_type" name="ach_account_type">
                            <option value="">Select…</option>
                            <option value="checking">Checking</option>
                            <option value="savings">Savings</option>
                        </select>
                    </label>
                    <label class="cp-ach-field">
                        <span>Account number *</span>
                        <input type="password" inputmode="numeric" id="ach_account_number" name="ach_account_number" maxlength="17" autocomplete="off">
                    </label>
                    <label class="cp-ach-field">
                        <span>Confirm account number *</span>
                        <input type="password" inputmode="numeric" id="ach_account_number_confirmation" name="ach_account_number_confirmation" maxlength="17" autocomplete="off">
                    </label>
                    <label class="cp-ach-field cp-ach-field--full">
                        <span>Account holder type</span>
                        <select id="ach_account_holder_type" name="ach_account_holder_type">
                            <option value="personal">Personal</option>
                            <option value="business">Business</option>
                        </select>
                    </label>
                </div>
                <p id="ach-loading-status" class="cp-ach-loading" hidden>Verifying bank account…</p>
            </div>
        `;

        injectStyles();

        if (cardSection && cardSection.parentNode) {
            cardSection.parentNode.insertBefore(wrapper, cardSection);
        } else {
            host.prepend(wrapper);
        }

        wrapper.querySelectorAll('.cp-payment-tab').forEach((tab) => {
            tab.addEventListener('click', () => setPaymentMethod(tab.dataset.method || PAYMENT_CARD));
        });
    }

    function findCardSection(host) {
        const selectors = [
            '[name="cardNumber"]',
            '#cardNumber',
            '.card-section',
            '.payment-card',
        ];

        for (const selector of selectors) {
            const el = host.querySelector(selector);

            if (el) {
                return el.closest('fieldset, .form-group, div') || el;
            }
        }

        return null;
    }

    function setPaymentMethod(method) {
        state.paymentMethod = method === PAYMENT_ACH ? PAYMENT_ACH : PAYMENT_CARD;

        document.querySelectorAll('.cp-payment-tab').forEach((tab) => {
            const active = tab.dataset.method === state.paymentMethod;
            tab.classList.toggle('cp-payment-tab--active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
        });

        const achPanel = document.getElementById('ach-payment-panel');
        const cardSection = findCardSection(document.getElementById('order_form') || document);

        if (achPanel) {
            achPanel.hidden = state.paymentMethod !== PAYMENT_ACH;
        }

        if (cardSection) {
            cardSection.style.display = state.paymentMethod === PAYMENT_ACH ? 'none' : '';
        }
    }

    function injectStyles() {
        if (document.getElementById('cp-ach-checkout-styles')) {
            return;
        }

        const style = document.createElement('style');
        style.id = 'cp-ach-checkout-styles';
        style.textContent = `
            .cp-payment-method-tabs { margin: 1rem 0; font-family: system-ui, sans-serif; }
            .cp-payment-tabs { display: flex; gap: .5rem; flex-wrap: wrap; }
            .cp-payment-tab {
                flex: 1 1 180px; display: flex; align-items: center; justify-content: center; gap: .5rem;
                padding: .75rem 1rem; border: 2px solid #d1d5db; border-radius: 8px; background: #fff;
                cursor: pointer; font-size: .95rem; font-weight: 600; color: #374151;
            }
            .cp-payment-tab--active { border-color: #111827; background: #f9fafb; }
            .cp-ach-panel {
                margin-top: 1rem; padding: 1rem; border: 1px solid #e5e7eb; border-radius: 8px; background: #fafafa;
            }
            .cp-ach-panel__lead { margin: 0 0 1rem; color: #4b5563; font-size: .9rem; }
            .cp-ach-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: .75rem; }
            .cp-ach-field { display: flex; flex-direction: column; gap: .25rem; font-size: .875rem; }
            .cp-ach-field--full { grid-column: 1 / -1; }
            .cp-ach-field input, .cp-ach-field select {
                padding: .55rem .65rem; border: 1px solid #d1d5db; border-radius: 6px; font-size: 1rem;
            }
            .cp-ach-loading { margin: .75rem 0 0; color: #1d4ed8; font-weight: 600; }
            @media (max-width: 640px) { .cp-payment-tab { flex: 1 1 100%; } }
        `;
        document.head.appendChild(style);
    }

    function boot() {
        const form = document.getElementById('order_form');

        if (!form) {
            return;
        }

        const cardAnchor = form.querySelector('[name="cardNumber"], #cardNumber, .card-section');

        ensurePaymentMethodTabs(cardAnchor || form);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    window.AchCheckout = {
        PAYMENT_CARD,
        PAYMENT_ACH,
        getPaymentMethod: () => state.paymentMethod,
        isAchSelected: () => state.paymentMethod === PAYMENT_ACH,
        validateAchFields,
        tokenize,
        setPaymentMethod,
    };
})();
