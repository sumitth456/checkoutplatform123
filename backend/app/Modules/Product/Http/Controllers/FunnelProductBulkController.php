<?php



namespace App\Modules\Product\Http\Controllers;



use App\Http\Controllers\Controller;

use App\Models\Funnel;

use App\Modules\Product\Import\Services\FunnelProductBulkService;

use App\Support\Admin\AdminAuthorization;

use Illuminate\Http\JsonResponse;

use Illuminate\Http\Request;



class FunnelProductBulkController extends Controller

{

    public function __construct(

        private readonly FunnelProductBulkService $bulk,

    ) {

        $this->middleware('auth:sanctum');

    }



    public function bulkAdd(Request $request, Funnel $funnel): JsonResponse

    {

        abort_unless(AdminAuthorization::can($request->user(), 'funnels.products.manage'), 403);



        $validated = $request->validate([

            'product_ids' => ['required', 'array', 'min:1'],

            'product_ids.*' => ['integer', 'exists:products,id'],

            'campaign_price' => ['nullable', 'numeric', 'min:0'],

        ]);



        $result = $this->bulk->bulkAdd(

            $funnel,

            $validated['product_ids'],

            isset($validated['campaign_price']) ? (float) $validated['campaign_price'] : null,

        );



        return response()->json($result);

    }



    public function bulkRemove(Request $request, Funnel $funnel): JsonResponse

    {

        abort_unless(AdminAuthorization::can($request->user(), 'funnels.products.manage'), 403);



        $validated = $request->validate([

            'product_ids' => ['required', 'array', 'min:1'],

            'product_ids.*' => ['integer', 'exists:products,id'],

        ]);



        return response()->json($this->bulk->bulkRemove($funnel, $validated['product_ids']));

    }



    public function bulkReplace(Request $request, Funnel $funnel): JsonResponse

    {

        abort_unless(AdminAuthorization::can($request->user(), 'funnels.products.manage'), 403);



        $validated = $request->validate([

            'product_ids' => ['required', 'array', 'min:1'],

            'product_ids.*' => ['integer', 'exists:products,id'],

        ]);



        return response()->json($this->bulk->bulkReplace($funnel, $validated['product_ids']));

    }



    public function bulkUpdatePrice(Request $request, Funnel $funnel): JsonResponse

    {

        abort_unless(AdminAuthorization::can($request->user(), 'funnels.products.manage'), 403);



        $validated = $request->validate([

            'updates' => ['required', 'array', 'min:1'],

            'updates.*.product_id' => ['required', 'integer', 'exists:products,id'],

            'updates.*.campaign_price' => ['nullable', 'numeric', 'min:0'],

            'updates.*.base_price' => ['nullable', 'numeric', 'min:0'],

        ]);



        return response()->json($this->bulk->bulkUpdatePrice($funnel, $validated['updates']));

    }



    public function bulkUpdateSubscription(Request $request, Funnel $funnel): JsonResponse

    {

        abort_unless(AdminAuthorization::can($request->user(), 'funnels.products.manage'), 403);



        $validated = $request->validate([

            'updates' => ['required', 'array', 'min:1'],

            'updates.*.product_id' => ['required', 'integer', 'exists:products,id'],

            'updates.*.is_subscription' => ['required', 'boolean'],

            'updates.*.subscription_plan' => ['nullable', 'string', 'max:255'],

        ]);



        return response()->json($this->bulk->bulkUpdateSubscription($funnel, $validated['updates']));

    }



    public function bulkUpdateInventory(Request $request, Funnel $funnel): JsonResponse

    {

        abort_unless(AdminAuthorization::can($request->user(), 'funnels.products.manage'), 403);



        $validated = $request->validate([

            'updates' => ['required', 'array', 'min:1'],

            'updates.*.product_id' => ['required', 'integer', 'exists:products,id'],

            'updates.*.inventory' => ['required', 'integer', 'min:0'],

            'updates.*.variant_sku' => ['nullable', 'string', 'max:64'],

        ]);



        return response()->json($this->bulk->bulkUpdateInventory($funnel, $validated['updates']));

    }

}


