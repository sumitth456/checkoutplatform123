<?php

namespace App\Modules\Affiliate\Http\Controllers;

use App\Models\Affiliate;
use App\Models\AffiliateTrackingLink;
use App\Modules\Affiliate\Services\AffiliateClickTrackingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

class AffiliateTrackingController
{
    public function __construct(
        private readonly AffiliateClickTrackingService $clicks,
    ) {}

    public function redirectLink(Request $request, string $code): RedirectResponse
    {
        $link = AffiliateTrackingLink::query()
            ->with('affiliate')
            ->where('code', $code)
            ->where('is_active', true)
            ->firstOrFail();

        $affiliate = $link->affiliate;

        if ($affiliate === null) {
            abort(404);
        }

        $click = $this->clicks->recordFromRequest($affiliate, $request, [
            'link_code' => $code,
            'landing_url' => $link->destination_url,
        ]);

        return redirect()
            ->away($link->generated_url ?: $link->destination_url)
            ->withCookie($this->affiliateCookie($affiliate, $click));
    }

    public function track(Request $request): RedirectResponse
    {
        $affiliate = $this->clicks->resolveAffiliateFromRequest($request);

        if ($affiliate === null) {
            return redirect()->to($request->query('redirect', '/'));
        }

        $click = $this->clicks->recordFromRequest($affiliate, $request, [
            'landing_url' => $request->query('redirect'),
        ]);

        $destination = (string) $request->query('redirect', '/');

        return redirect()
            ->to($destination)
            ->withCookie($this->affiliateCookie($affiliate, $click));
    }

    private function affiliateCookie(Affiliate $affiliate, \App\Models\AffiliateClick $click): Cookie
    {
        $minutes = max(1, (int) $affiliate->cookie_duration_days) * 24 * 60;

        return cookie(
            'affiliate_attribution',
            json_encode([
                'affiliate_id' => $affiliate->id,
                'affiliate_tracking_id' => $affiliate->tracking_id,
                'affiliate_click_id' => $click->id,
                'click_uuid' => $click->click_id,
            ]),
            $minutes,
            '/',
            null,
            false,
            false,
        );
    }
}
