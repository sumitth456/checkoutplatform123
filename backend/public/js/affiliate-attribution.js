/**
 * Reads affiliate attribution cookie set by /r/{code} and /track redirects.
 */
(function () {
    function readCookie(name) {
        const match = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/[$()*+.?[\\\]^{|}]/g, '\\$&') + '=([^;]*)'));

        return match ? decodeURIComponent(match[1]) : null;
    }

    function parseAttribution() {
        const raw = readCookie('affiliate_attribution');

        if (! raw) {
            return {};
        }

        try {
            const data = JSON.parse(raw);

            return data && typeof data === 'object' ? data : {};
        } catch {
            return {};
        }
    }

    window.AffiliateAttribution = {
        parse: parseAttribution,
        mergeMetadata(metadata) {
            const attribution = parseAttribution();
            const merged = Object.assign({}, metadata || {});

            if (attribution.affiliate_id && ! merged.affiliate_id) {
                merged.affiliate_id = attribution.affiliate_id;
            }

            if (attribution.affiliate_click_id && ! merged.affiliate_click_id) {
                merged.affiliate_click_id = attribution.affiliate_click_id;
            }

            if (attribution.affiliate_tracking_id && ! merged.affiliate_tracking_id) {
                merged.affiliate_tracking_id = attribution.affiliate_tracking_id;
            }

            if (attribution.click_uuid && ! merged.affiliate_click_uuid) {
                merged.affiliate_click_uuid = attribution.click_uuid;
            }

            if (attribution.affiliate_offer_id && ! merged.affiliate_offer_id) {
                merged.affiliate_offer_id = attribution.affiliate_offer_id;
            }

            return merged;
        },
    };
})();
