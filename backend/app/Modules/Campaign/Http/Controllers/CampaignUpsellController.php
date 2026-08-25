<?php

namespace App\Modules\Campaign\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignDownsell;
use App\Models\CampaignOrderBump;
use App\Models\CampaignUpsell;
use App\Modules\Campaign\Http\Resources\CampaignDownsellResource;
use App\Modules\Campaign\Http\Resources\CampaignOrderBumpResource;
use App\Modules\Campaign\Http\Resources\CampaignUpsellResource;
use App\Modules\Campaign\Services\CampaignMerchandisingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CampaignUpsellController extends Controller
{
    public function __construct(
        private readonly CampaignMerchandisingService $merchandising,
    ) {
        $this->middleware('auth:sanctum');
    }

    public function index(Campaign $campaign): JsonResponse
    {
        $this->authorize('viewAny', CampaignUpsell::class);

        $items = $campaign->upsells()->with('product')->orderByDesc('priority')->orderBy('sort_order')->get();

        return response()->json(['data' => CampaignUpsellResource::collection($items)]);
    }

    public function store(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('create', CampaignUpsell::class);

        $validated = $this->validateUpsell($request);

        $upsell = $this->merchandising->createUpsell($campaign, $validated);

        return response()->json(['data' => new CampaignUpsellResource($upsell->load('product'))], 201);
    }

    public function update(Request $request, Campaign $campaign, CampaignUpsell $upsell): CampaignUpsellResource
    {
        $this->authorize('update', $upsell);
        abort_unless($upsell->campaign_id === $campaign->id, 404);

        return new CampaignUpsellResource(
            $this->merchandising->updateUpsell($upsell, $this->validateUpsell($request, partial: true))
        );
    }

    public function destroy(Campaign $campaign, CampaignUpsell $upsell): JsonResponse
    {
        $this->authorize('delete', $upsell);
        abort_unless($upsell->campaign_id === $campaign->id, 404);

        $this->merchandising->deleteUpsell($upsell);

        return response()->json(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateUpsell(Request $request, bool $partial = false): array
    {
        $rules = [
            'product_id' => [$partial ? 'sometimes' : 'required', 'integer', 'exists:products,id'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'trigger' => ['nullable', 'in:always,cart_contains_product,cart_min_subtotal,declined_upsell,post_purchase'],
            'trigger_config' => ['nullable', 'array'],
            'position' => ['nullable', 'in:pre_checkout,post_checkout,one_click_upsell,thank_you,checkout_sidebar'],
            'discount_type' => ['nullable', 'in:none,percent,fixed_amount'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];

        return $request->validate($rules);
    }
}
