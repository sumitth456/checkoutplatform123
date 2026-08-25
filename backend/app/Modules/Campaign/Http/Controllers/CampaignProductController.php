<?php

namespace App\Modules\Campaign\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Modules\Campaign\Http\Resources\CampaignProductResource;
use App\Modules\Campaign\Services\CampaignProductBulkService;
use App\Support\Admin\AdminAuthorization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CampaignProductController extends Controller
{
    public function __construct(
        private readonly CampaignProductBulkService $products,
    ) {
        $this->middleware('auth:sanctum');
    }

    public function index(Campaign $campaign): AnonymousResourceCollection
    {
        abort_unless(AdminAuthorization::can(request()->user(), 'campaigns.products.manage'), 403);

        $products = $campaign->products()
            ->with('variants')
            ->orderByPivot('priority', 'desc')
            ->orderByPivot('sort_order')
            ->get();

        return CampaignProductResource::collection($products);
    }

    public function bulkAdd(Request $request, Campaign $campaign): JsonResponse
    {
        abort_unless(AdminAuthorization::can($request->user(), 'campaigns.products.manage'), 403);

        $validated = $request->validate([
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['integer', 'exists:products,id'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'campaign_price' => ['nullable', 'numeric', 'min:0'],
            'min_quantity' => ['nullable', 'integer', 'min:0'],
            'max_quantity' => ['nullable', 'integer', 'min:0'],
            'inventory_rule' => ['nullable', 'in:inherit,enforce_limits,ignore_inventory,block_oversell'],
        ]);

        $result = $this->products->bulkAdd(
            $campaign,
            $validated['product_ids'],
            collect($validated)->except('product_ids')->all(),
        );

        return response()->json($result);
    }

    public function bulkRemove(Request $request, Campaign $campaign): JsonResponse
    {
        abort_unless(AdminAuthorization::can($request->user(), 'campaigns.products.manage'), 403);

        $validated = $request->validate([
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['integer', 'exists:products,id'],
        ]);

        return response()->json($this->products->bulkRemove($campaign, $validated['product_ids']));
    }

    public function bulkReplace(Request $request, Campaign $campaign): JsonResponse
    {
        abort_unless(AdminAuthorization::can($request->user(), 'campaigns.products.manage'), 403);

        $validated = $request->validate([
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['integer', 'exists:products,id'],
        ]);

        return response()->json($this->products->bulkReplace(
            $campaign,
            $validated['product_ids'],
        ));
    }

    public function updateSortOrder(Request $request, Campaign $campaign): JsonResponse
    {
        abort_unless(AdminAuthorization::can($request->user(), 'campaigns.products.manage'), 403);

        $validated = $request->validate([
            'order' => ['required', 'array', 'min:1'],
            'order.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'order.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        return response()->json($this->products->updateSortOrder($campaign, $validated['order']));
    }

    public function updatePriority(Request $request, Campaign $campaign): JsonResponse
    {
        abort_unless(AdminAuthorization::can($request->user(), 'campaigns.products.manage'), 403);

        $validated = $request->validate([
            'updates' => ['required', 'array', 'min:1'],
            'updates.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'updates.*.priority' => ['required', 'integer', 'min:0', 'max:9999'],
        ]);

        return response()->json($this->products->updatePriority($campaign, $validated['updates']));
    }

    public function updateRules(Request $request, Campaign $campaign): JsonResponse
    {
        abort_unless(AdminAuthorization::can($request->user(), 'campaigns.products.manage'), 403);

        $validated = $request->validate([
            'updates' => ['required', 'array', 'min:1'],
            'updates.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'updates.*.min_quantity' => ['nullable', 'integer', 'min:0'],
            'updates.*.max_quantity' => ['nullable', 'integer', 'min:0'],
            'updates.*.inventory_rule' => ['nullable', 'in:inherit,enforce_limits,ignore_inventory,block_oversell'],
            'updates.*.campaign_price' => ['nullable', 'numeric', 'min:0'],
            'updates.*.variant_settings' => ['nullable', 'array'],
            'updates.*.is_active' => ['nullable', 'boolean'],
        ]);

        return response()->json($this->products->bulkUpdateRules($campaign, $validated['updates']));
    }
}
