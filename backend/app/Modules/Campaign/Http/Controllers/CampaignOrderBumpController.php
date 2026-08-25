<?php

namespace App\Modules\Campaign\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignOrderBump;
use App\Modules\Campaign\Http\Resources\CampaignOrderBumpResource;
use App\Modules\Campaign\Services\CampaignMerchandisingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CampaignOrderBumpController extends Controller
{
    public function __construct(
        private readonly CampaignMerchandisingService $merchandising,
    ) {
        $this->middleware('auth:sanctum');
    }

    public function index(Campaign $campaign): JsonResponse
    {
        $this->authorize('viewAny', CampaignOrderBump::class);

        $items = $campaign->orderBumps()->with('product')->orderByDesc('priority')->orderBy('sort_order')->get();

        return response()->json(['data' => CampaignOrderBumpResource::collection($items)]);
    }

    public function store(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('create', CampaignOrderBump::class);

        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'bump_price' => ['nullable', 'numeric', 'min:0'],
            'position' => ['nullable', 'in:pre_checkout,post_checkout,one_click_upsell,thank_you,checkout_sidebar'],
            'display_rule' => ['nullable', 'in:always,cart_min_subtotal,cart_contains_product,first_order'],
            'display_rule_config' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $row = $this->merchandising->createOrderBump($campaign, $validated);

        return response()->json(['data' => new CampaignOrderBumpResource($row->load('product'))], 201);
    }

    public function update(Request $request, Campaign $campaign, CampaignOrderBump $orderBump): CampaignOrderBumpResource
    {
        $this->authorize('update', $orderBump);
        abort_unless($orderBump->campaign_id === $campaign->id, 404);

        $validated = $request->validate([
            'product_id' => ['sometimes', 'integer', 'exists:products,id'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'bump_price' => ['nullable', 'numeric', 'min:0'],
            'position' => ['nullable', 'in:pre_checkout,post_checkout,one_click_upsell,thank_you,checkout_sidebar'],
            'display_rule' => ['nullable', 'in:always,cart_min_subtotal,cart_contains_product,first_order'],
            'display_rule_config' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return new CampaignOrderBumpResource($this->merchandising->updateOrderBump($orderBump, $validated));
    }

    public function destroy(Campaign $campaign, CampaignOrderBump $orderBump): JsonResponse
    {
        $this->authorize('delete', $orderBump);
        abort_unless($orderBump->campaign_id === $campaign->id, 404);

        $this->merchandising->deleteOrderBump($orderBump);

        return response()->json(null, 204);
    }
}
