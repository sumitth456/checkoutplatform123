<?php

namespace App\Modules\Campaign\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Modules\Campaign\Http\Requests\CalculateCampaignCheckoutRequest;
use App\Modules\Campaign\Http\Resources\CampaignCheckoutResource;
use App\Modules\Campaign\Services\CampaignCheckoutCalculateService;
use App\Modules\Campaign\Services\CampaignCheckoutContextService;
use Illuminate\Http\JsonResponse;

class CampaignCheckoutController extends Controller
{
    public function __construct(
        private readonly CampaignCheckoutContextService $context,
        private readonly CampaignCheckoutCalculateService $calculate,
    ) {}

    public function show(Campaign $campaign): CampaignCheckoutResource
    {
        $campaign = $this->context->findCheckoutCampaign($campaign->uuid);

        return new CampaignCheckoutResource($this->context->build($campaign));
    }

    public function calculate(CalculateCampaignCheckoutRequest $request, Campaign $campaign): JsonResponse
    {
        $campaign = $this->context->findCheckoutCampaign($campaign->uuid);

        return response()->json([
            'data' => $this->calculate->calculate($campaign, $request->validated()),
        ]);
    }
}
