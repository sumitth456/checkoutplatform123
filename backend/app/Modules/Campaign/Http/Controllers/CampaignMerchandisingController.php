<?php

namespace App\Modules\Campaign\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignUpsell;
use App\Modules\Campaign\Services\CampaignMerchandisingService;
use Illuminate\Http\JsonResponse;

class CampaignMerchandisingController extends Controller
{
    public function __construct(
        private readonly CampaignMerchandisingService $merchandising,
    ) {
        $this->middleware('auth:sanctum');
    }

    public function show(Campaign $campaign): JsonResponse
    {
        $this->authorize('viewAny', CampaignUpsell::class);

        return response()->json([
            'data' => $this->merchandising->checkoutPayload($campaign),
        ]);
    }
}
