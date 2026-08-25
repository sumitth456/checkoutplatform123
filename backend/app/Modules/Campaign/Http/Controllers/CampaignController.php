<?php

namespace App\Modules\Campaign\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Modules\Campaign\DTOs\CreateCampaignDTO;
use App\Modules\Campaign\DTOs\UpdateCampaignDTO;
use App\Modules\Campaign\Http\Resources\CampaignResource;
use App\Modules\Campaign\Services\CampaignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CampaignController extends Controller
{
    public function __construct(
        private readonly CampaignService $campaigns,
    ) {
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Campaign::class);

        $paginator = $this->campaigns->list(
            filters: $request->only(['status', 'search', 'brand_id']),
            perPage: (int) $request->integer('per_page', 15),
        );

        return CampaignResource::collection($paginator);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Campaign::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'internal_notes' => ['nullable', 'string'],
            'status' => ['nullable', 'in:draft,active,paused,archived'],
            'brand_id' => ['nullable', 'integer', 'exists:product_brands,id'],
            'store_name' => ['nullable', 'string', 'max:255'],
            'store_code' => ['nullable', 'string', 'max:64'],
            'currency' => ['nullable', 'string', 'size:3'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'metadata' => ['nullable', 'array'],
        ]);

        $campaign = $this->campaigns->create(CreateCampaignDTO::fromArray($validated));

        return (new CampaignResource($campaign->load('brand')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Campaign $campaign): CampaignResource
    {
        $this->authorize('view', $campaign);

        return new CampaignResource($campaign->load('brand'));
    }

    public function update(Request $request, Campaign $campaign): CampaignResource
    {
        $this->authorize('update', $campaign);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'internal_notes' => ['nullable', 'string'],
            'status' => ['sometimes', 'in:draft,active,paused,archived'],
            'brand_id' => ['nullable', 'integer', 'exists:product_brands,id'],
            'store_name' => ['nullable', 'string', 'max:255'],
            'store_code' => ['nullable', 'string', 'max:64'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'timezone' => ['sometimes', 'string', 'max:64'],
            'metadata' => ['nullable', 'array'],
        ]);

        $updated = $this->campaigns->update($campaign, UpdateCampaignDTO::fromArray(array_merge(
            ['name' => $campaign->name, 'status' => $campaign->status->value, 'currency' => $campaign->currency, 'timezone' => $campaign->timezone],
            $validated,
        )));

        return new CampaignResource($updated->load('brand'));
    }

    public function destroy(Campaign $campaign): JsonResponse
    {
        $this->authorize('delete', $campaign);

        $this->campaigns->delete($campaign);

        return response()->json(null, 204);
    }

    public function duplicate(Campaign $campaign): JsonResponse
    {
        $this->authorize('duplicate', $campaign);

        $copy = $this->campaigns->duplicate($campaign);

        return (new CampaignResource($copy->load('brand')))
            ->response()
            ->setStatusCode(201);
    }

    public function archive(Campaign $campaign): CampaignResource
    {
        $this->authorize('archive', $campaign);

        $archived = $this->campaigns->archive($campaign);

        return new CampaignResource($archived->load('brand'));
    }
}
