<?php

namespace App\Modules\Affiliate\Http\Controllers\Api;

use App\Models\Affiliate;
use App\Models\AffiliateClick;
use App\Models\AffiliateCommission;
use App\Models\AffiliateConversion;
use App\Models\AffiliatePayout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AffiliateApiController
{
    public function profile(Request $request): JsonResponse
    {
        $affiliate = $this->authenticate($request);

        return response()->json([
            'uuid' => $affiliate->uuid,
            'name' => $affiliate->name,
            'email' => $affiliate->email,
            'tracking_id' => $affiliate->tracking_id,
            'status' => $affiliate->status->value,
            'pending_commission' => $affiliate->pending_commission,
            'paid_commission' => $affiliate->paid_commission,
            'total_clicks' => $affiliate->total_clicks,
            'total_conversions' => $affiliate->total_conversions,
        ]);
    }

    public function clicks(Request $request): JsonResponse
    {
        $affiliate = $this->authenticate($request);

        $clicks = AffiliateClick::query()
            ->where('affiliate_id', $affiliate->id)
            ->latest('clicked_at')
            ->limit(100)
            ->get(['uuid', 'click_id', 'clicked_at', 'is_unique', 'landing_url', 'country_code']);

        return response()->json(['data' => $clicks]);
    }

    public function conversions(Request $request): JsonResponse
    {
        $affiliate = $this->authenticate($request);

        $rows = AffiliateConversion::query()
            ->where('affiliate_id', $affiliate->id)
            ->latest('converted_at')
            ->limit(100)
            ->get(['uuid', 'conversion_type', 'revenue', 'commission_amount', 'currency', 'converted_at']);

        return response()->json(['data' => $rows]);
    }

    public function commissions(Request $request): JsonResponse
    {
        $affiliate = $this->authenticate($request);

        $rows = AffiliateCommission::query()
            ->where('affiliate_id', $affiliate->id)
            ->latest('earned_at')
            ->limit(100)
            ->get(['uuid', 'status', 'commission_amount', 'currency', 'earned_at', 'rule_source']);

        return response()->json(['data' => $rows]);
    }

    public function payouts(Request $request): JsonResponse
    {
        $affiliate = $this->authenticate($request);

        $rows = AffiliatePayout::query()
            ->where('affiliate_id', $affiliate->id)
            ->latest('created_at')
            ->limit(50)
            ->get(['uuid', 'status', 'amount', 'currency', 'paid_at']);

        return response()->json(['data' => $rows]);
    }

    private function authenticate(Request $request): Affiliate
    {
        $apiKey = $request->bearerToken()
            ?? $request->header('X-Affiliate-Api-Key')
            ?? $request->query('api_key');

        $affiliate = Affiliate::query()->where('api_key', $apiKey)->first();

        abort_if($affiliate === null, 401, 'Invalid affiliate API key.');

        return $affiliate;
    }
}
