<?php

namespace App\Modules\Campaign\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignProduct;
use App\Models\CampaignProductBillingCycle;
use App\Modules\Campaign\Http\Resources\CampaignProductBillingCycleResource;
use App\Modules\Campaign\Services\CampaignProductBillingCycleService;
use App\Modules\Product\BillingCycles\BillingCycleExportImportService;
use App\Modules\Product\BillingCycles\BillingCycleRequestRules;
use App\Support\Admin\AdminAuthorization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CampaignProductBillingCycleController extends Controller
{
    public function __construct(
        private readonly CampaignProductBillingCycleService $cycles,
        private readonly BillingCycleExportImportService $transfer,
    ) {}

    public function index(Campaign $campaign, CampaignProduct $campaignProduct): AnonymousResourceCollection
    {
        abort_unless(AdminAuthorization::can(request()->user(), 'campaigns.products.manage'), 403);
        $this->assertCampaignProduct($campaign, $campaignProduct);

        return CampaignProductBillingCycleResource::collection(
            $this->cycles->list($campaignProduct),
        );
    }

    public function store(Request $request, Campaign $campaign, CampaignProduct $campaignProduct): JsonResponse
    {
        abort_unless(AdminAuthorization::can($request->user(), 'campaigns.products.manage'), 403);
        $this->assertCampaignProduct($campaign, $campaignProduct);

        $validated = $request->validate(BillingCycleRequestRules::cycleRules(requireCycleNumber: false));

        $cycle = $this->cycles->addCycle($campaignProduct, $validated);

        return (new CampaignProductBillingCycleResource($cycle->load(['product', 'variant'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(
        Campaign $campaign,
        CampaignProduct $campaignProduct,
        CampaignProductBillingCycle $billingCycle,
    ): CampaignProductBillingCycleResource {
        abort_unless(AdminAuthorization::can(request()->user(), 'campaigns.products.manage'), 403);
        $this->assertCampaignProduct($campaign, $campaignProduct);
        $this->assertOwnedByLine($campaignProduct, $billingCycle);

        return new CampaignProductBillingCycleResource($billingCycle->load(['product', 'variant']));
    }

    public function update(
        Request $request,
        Campaign $campaign,
        CampaignProduct $campaignProduct,
        CampaignProductBillingCycle $billingCycle,
    ): CampaignProductBillingCycleResource {
        abort_unless(AdminAuthorization::can($request->user(), 'campaigns.products.manage'), 403);
        $this->assertCampaignProduct($campaign, $campaignProduct);
        $this->assertOwnedByLine($campaignProduct, $billingCycle);

        $validated = $request->validate(BillingCycleRequestRules::cycleRules());

        return new CampaignProductBillingCycleResource(
            $this->cycles->updateCycle($billingCycle, $validated)->load(['product', 'variant']),
        );
    }

    public function destroy(
        Campaign $campaign,
        CampaignProduct $campaignProduct,
        CampaignProductBillingCycle $billingCycle,
    ): JsonResponse {
        abort_unless(AdminAuthorization::can(request()->user(), 'campaigns.products.manage'), 403);
        $this->assertCampaignProduct($campaign, $campaignProduct);
        $this->assertOwnedByLine($campaignProduct, $billingCycle);

        $this->cycles->deleteCycle($billingCycle);

        return response()->json(null, 204);
    }

    public function sync(Request $request, Campaign $campaign, CampaignProduct $campaignProduct): JsonResponse
    {
        abort_unless(AdminAuthorization::can($request->user(), 'campaigns.products.manage'), 403);
        $this->assertCampaignProduct($campaign, $campaignProduct);

        $validated = $request->validate(BillingCycleRequestRules::syncRules());
        $synced = $this->cycles->syncCycles($campaignProduct, $validated['cycles']);

        return response()->json([
            'synced' => $synced,
            'cycles' => CampaignProductBillingCycleResource::collection($this->cycles->list($campaignProduct)),
        ]);
    }

    public function inherit(Campaign $campaign, CampaignProduct $campaignProduct): JsonResponse
    {
        abort_unless(AdminAuthorization::can(request()->user(), 'campaigns.products.manage'), 403);
        $this->assertCampaignProduct($campaign, $campaignProduct);

        $imported = $this->cycles->inheritForCampaignProduct($campaignProduct);

        return response()->json([
            'imported' => $imported,
            'cycles' => CampaignProductBillingCycleResource::collection($this->cycles->list($campaignProduct)),
        ]);
    }

    public function exportLine(Request $request, Campaign $campaign, CampaignProduct $campaignProduct): BinaryFileResponse|JsonResponse
    {
        abort_unless(AdminAuthorization::can($request->user(), 'campaigns.products.manage'), 403);
        $this->assertCampaignProduct($campaign, $campaignProduct);

        $format = (string) $request->query('format', 'json');
        $result = $this->transfer->exportCampaignProduct($campaignProduct, $format);

        if ($format === 'json' && $request->boolean('inline')) {
            return response()->json(json_decode((string) file_get_contents($result['path']), true));
        }

        return response()->download($result['path'], $result['filename'])->deleteFileAfterSend();
    }

    public function importLine(Request $request, Campaign $campaign, CampaignProduct $campaignProduct): JsonResponse
    {
        abort_unless(AdminAuthorization::can($request->user(), 'campaigns.products.manage'), 403);
        $this->assertCampaignProduct($campaign, $campaignProduct);

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

        return response()->json(
            $this->transfer->importCampaignProduct($campaignProduct, $payload, $path),
        );
    }

    public function exportCampaign(Request $request, Campaign $campaign): BinaryFileResponse|JsonResponse
    {
        abort_unless(AdminAuthorization::can($request->user(), 'campaigns.products.manage'), 403);

        $format = (string) $request->query('format', 'json');
        $result = $this->transfer->exportCampaign($campaign, $format);

        if ($format === 'json' && $request->boolean('inline')) {
            return response()->json(json_decode((string) file_get_contents($result['path']), true));
        }

        return response()->download($result['path'], $result['filename'])->deleteFileAfterSend();
    }

    private function assertCampaignProduct(Campaign $campaign, CampaignProduct $campaignProduct): void
    {
        abort_unless($campaignProduct->campaign_id === $campaign->id, 404);
    }

    private function assertOwnedByLine(CampaignProduct $campaignProduct, CampaignProductBillingCycle $billingCycle): void
    {
        abort_unless($billingCycle->campaign_product_id === $campaignProduct->id, 404);
    }
}
