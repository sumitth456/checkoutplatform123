<?php

namespace App\Modules\Product\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductBillingCycle;
use App\Modules\Product\BillingCycles\BillingCycleExportImportService;
use App\Modules\Product\BillingCycles\BillingCycleRequestRules;
use App\Modules\Product\BillingCycles\ProductBillingCycleService;
use App\Modules\Product\Http\Resources\ProductBillingCycleResource;
use App\Support\Admin\AdminAuthorization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProductBillingCycleController extends Controller
{
    public function __construct(
        private readonly ProductBillingCycleService $cycles,
        private readonly BillingCycleExportImportService $transfer,
    ) {}

    public function index(Product $product): AnonymousResourceCollection
    {
        abort_unless(AdminAuthorization::can(request()->user(), 'products.billing_cycles.manage'), 403);

        return ProductBillingCycleResource::collection(
            $this->cycles->list($product),
        );
    }

    public function store(Request $request, Product $product): JsonResponse
    {
        abort_unless(AdminAuthorization::can($request->user(), 'products.billing_cycles.manage'), 403);

        $validated = $request->validate(BillingCycleRequestRules::cycleRules(requireCycleNumber: false));

        $cycle = $this->cycles->addCycle($product, $validated);

        return (new ProductBillingCycleResource($cycle->load(['billProduct', 'variant'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Product $product, ProductBillingCycle $billingCycle): ProductBillingCycleResource
    {
        abort_unless(AdminAuthorization::can(request()->user(), 'products.billing_cycles.manage'), 403);
        $this->assertOwnedByProduct($product, $billingCycle);

        return new ProductBillingCycleResource($billingCycle->load(['billProduct', 'variant']));
    }

    public function update(Request $request, Product $product, ProductBillingCycle $billingCycle): ProductBillingCycleResource
    {
        abort_unless(AdminAuthorization::can($request->user(), 'products.billing_cycles.manage'), 403);
        $this->assertOwnedByProduct($product, $billingCycle);

        $validated = $request->validate(BillingCycleRequestRules::cycleRules());

        return new ProductBillingCycleResource(
            $this->cycles->updateCycle($billingCycle, $validated)->load(['billProduct', 'variant']),
        );
    }

    public function destroy(Product $product, ProductBillingCycle $billingCycle): JsonResponse
    {
        abort_unless(AdminAuthorization::can(request()->user(), 'products.billing_cycles.manage'), 403);
        $this->assertOwnedByProduct($product, $billingCycle);

        $this->cycles->deleteCycle($billingCycle);

        return response()->json(null, 204);
    }

    public function sync(Request $request, Product $product): JsonResponse
    {
        abort_unless(AdminAuthorization::can($request->user(), 'products.billing_cycles.manage'), 403);

        $validated = $request->validate(BillingCycleRequestRules::syncRules());
        $imported = $this->cycles->syncProductCycles($product, $validated['cycles']);

        return response()->json([
            'synced' => $imported,
            'cycles' => ProductBillingCycleResource::collection($this->cycles->list($product)),
        ]);
    }

    public function seedDefaults(Product $product): AnonymousResourceCollection
    {
        abort_unless(AdminAuthorization::can(request()->user(), 'products.billing_cycles.manage'), 403);

        $this->cycles->ensureDefaultTemplate($product);

        return ProductBillingCycleResource::collection($this->cycles->list($product));
    }

    public function export(Request $request, Product $product): BinaryFileResponse|JsonResponse
    {
        abort_unless(AdminAuthorization::can($request->user(), 'products.billing_cycles.export'), 403);

        $format = (string) $request->query('format', 'json');
        $result = $this->transfer->exportProduct($product, $format);

        if ($format === 'json' && $request->boolean('inline')) {
            return response()->json(json_decode((string) file_get_contents($result['path']), true));
        }

        return response()->download($result['path'], $result['filename'])->deleteFileAfterSend();
    }

    public function import(Request $request, Product $product): JsonResponse
    {
        abort_unless(AdminAuthorization::can($request->user(), 'products.billing_cycles.import'), 403);

        if ($request->hasFile('file')) {
            $request->validate([
                'file' => ['required', 'file', 'mimes:csv,txt,json'],
            ]);

            $path = $request->file('file')?->getRealPath();
            $payload = null;
        } else {
            $payload = $request->validate([
                'cycles' => ['required', 'array', 'min:1'],
                'cycles.*' => ['array'],
            ]);
            $path = null;
        }

        $result = $this->transfer->importProduct($product, $payload, $path);

        return response()->json($result);
    }

    private function assertOwnedByProduct(Product $product, ProductBillingCycle $billingCycle): void
    {
        abort_unless($billingCycle->product_id === $product->id, 404);
    }
}
