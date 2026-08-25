/**
 * Client-side fallback for legacy CheckoutChamp placeholders.
 */
(function () {
    const contextEl = document.getElementById('landing-page-checkout-context');

    if (!contextEl) {
        return;
    }

    let context;

    try {
        context = JSON.parse(contextEl.textContent || '{}');
    } catch {
        return;
    }

    const product = (context.products || [])[0] || {};
    const mensProduct = (context.products || []).find((p) => (p.audience || p.gender || '').toString().includes('mens') || (p.sku || '').toUpperCase().includes('MENS')) || product;
    const womensProduct = (context.products || []).find((p) => (p.audience || p.gender || '').toString().includes('womens') || (p.sku || '').toUpperCase().includes('WOMENS')) || (context.products || [])[1] || product;
    const variant = (product.variants || [])[0] || {};
    const vars = context.variables || {};

    const replacements = Object.assign({
        productName: product.name || mensProduct.name || '',
        'prd1-name': product.name || mensProduct.name || '',
        'prd1-prc': variant.price || product.campaign_price || mensProduct.campaign_price || '0.00',
        'sf-error-message': '',
    }, vars);

    const year = String(new Date().getFullYear());

    document.querySelectorAll('#imatau, [id="iqsju4"]').forEach((el) => {
        el.style.display = 'none';
        el.textContent = '';
    });

    const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
    const nodes = [];

    while (walker.nextNode()) {
        nodes.push(walker.currentNode);
    }

    nodes.forEach((node) => {
        let text = node.nodeValue || '';

        if (!text.includes('{{') && !text.includes('${{')) {
            return;
        }

        Object.entries(replacements).forEach(([key, value]) => {
            text = text.split(`{{${key}}}`).join(String(value));
            text = text.split(`\${{${key}}}`).join(`$${value}`);
        });

        text = text.replace(/\{\{#if\s+sf-error-message\s*\}\}/gi, '');
        text = text.replace(/\{\{\/if\s*\}\}/gi, '');
        text = text.replace(/\{\{\s*moment[^}]+\}\}/gi, year);

        node.nodeValue = text;
    });

    document.querySelectorAll('option[value*="{{moment"], option[data-plan-price*="{{"]').forEach((el) => {
        if (el.getAttribute('value')?.includes('{{moment')) {
            el.setAttribute('value', year);
        }

        const price = replacements['prd1-prc'] || variant.price;

        if (el.dataset.planPrice?.includes('{{')) {
            el.dataset.planPrice = price;
        }
    });
})();
