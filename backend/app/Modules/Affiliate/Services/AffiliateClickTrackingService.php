<?php

namespace App\Modules\Affiliate\Services;

use App\Models\Affiliate;
use App\Models\AffiliateClick;
use App\Models\AffiliateOffer;
use App\Models\AffiliateTrackingLink;
use App\Models\LandingPage;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AffiliateClickTrackingService
{
    /**
     * @param  array<string, mixed>  $params
     */
    public function recordFromRequest(Affiliate $affiliate, Request $request, array $params = []): AffiliateClick
    {
        $offer = isset($params['offer_id'])
            ? AffiliateOffer::query()->find($params['offer_id'])
            : null;

        $trackingLink = isset($params['link_code'])
            ? AffiliateTrackingLink::query()->where('code', $params['link_code'])->first()
            : null;

        $ip = (string) $request->ip();
        $userAgent = (string) $request->userAgent();
        $isUnique = ! AffiliateClick::query()
            ->where('affiliate_id', $affiliate->id)
            ->where('ip_address', $ip)
            ->where('clicked_at', '>=', now()->subDay())
            ->exists();

        $click = AffiliateClick::query()->create([
            'click_id' => 'clk_'.Str::uuid(),
            'affiliate_id' => $affiliate->id,
            'offer_id' => $offer?->id ?? $trackingLink?->offer_id,
            'platform_campaign_id' => $params['campaign_id'] ?? $trackingLink?->platform_campaign_id,
            'tracking_link_id' => $trackingLink?->id,
            'landing_page_id' => $params['landing_page_id'] ?? $trackingLink?->landing_page_id,
            'sub_id_1' => $params['sub1'] ?? $params['sub_id_1'] ?? null,
            'sub_id_2' => $params['sub2'] ?? $params['sub_id_2'] ?? null,
            'sub_id_3' => $params['sub3'] ?? $params['sub_id_3'] ?? null,
            'sub_id_4' => $params['sub4'] ?? $params['sub_id_4'] ?? null,
            'sub_id_5' => $params['sub5'] ?? $params['sub_id_5'] ?? null,
            'utm_source' => $params['utm_source'] ?? $request->query('utm_source'),
            'utm_medium' => $params['utm_medium'] ?? $request->query('utm_medium'),
            'utm_campaign' => $params['utm_campaign'] ?? $request->query('utm_campaign'),
            'utm_content' => $params['utm_content'] ?? $request->query('utm_content'),
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'browser' => $this->detectBrowser($userAgent),
            'os' => $this->detectOs($userAgent),
            'device_type' => $this->detectDeviceType($userAgent),
            'country_code' => $params['country_code'] ?? null,
            'landing_url' => $params['landing_url'] ?? $request->fullUrl(),
            'referrer_url' => $request->headers->get('referer'),
            'is_unique' => $isUnique,
            'clicked_at' => now(),
        ]);

        $affiliate->increment('total_clicks');
        $trackingLink?->increment('click_count');

        return $click;
    }

    public function resolveAffiliateFromRequest(Request $request): ?Affiliate
    {
        $trackingCode = $request->query('aff')
            ?? $request->query('affiliate')
            ?? $request->query('ref');

        if (blank($trackingCode)) {
            return null;
        }

        return Affiliate::query()
            ->where('tracking_id', $trackingCode)
            ->orWhere('uuid', $trackingCode)
            ->orWhere('id', is_numeric($trackingCode) ? (int) $trackingCode : -1)
            ->where('status', 'active')
            ->first();
    }

    public function buildDestinationUrl(LandingPage $page, Affiliate $affiliate, ?AffiliateOffer $offer = null): string
    {
        $base = url($page->publicUrl());
        $query = http_build_query(array_filter([
            'aff' => $affiliate->tracking_id,
            'offer' => $offer?->uuid,
        ]));

        return str_contains($base, '?') ? "{$base}&{$query}" : "{$base}?{$query}";
    }

    private function detectBrowser(string $userAgent): ?string
    {
        return match (true) {
            str_contains($userAgent, 'Chrome') => 'Chrome',
            str_contains($userAgent, 'Firefox') => 'Firefox',
            str_contains($userAgent, 'Safari') => 'Safari',
            str_contains($userAgent, 'Edg') => 'Edge',
            default => null,
        };
    }

    private function detectOs(string $userAgent): ?string
    {
        return match (true) {
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Mac OS') => 'macOS',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'iPhone'), str_contains($userAgent, 'iPad') => 'iOS',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => null,
        };
    }

    private function detectDeviceType(string $userAgent): string
    {
        if (str_contains($userAgent, 'Mobile')) {
            return 'mobile';
        }

        if (str_contains($userAgent, 'Tablet') || str_contains($userAgent, 'iPad')) {
            return 'tablet';
        }

        return 'desktop';
    }
}
