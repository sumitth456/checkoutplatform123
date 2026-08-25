<?php

namespace App\Modules\Variant\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Variant;
use App\Modules\Variant\Http\Requests\ListVariantsRequest;
use App\Modules\Variant\Http\Requests\StoreVariantRequest;
use App\Modules\Variant\Http\Requests\UpdateVariantRequest;
use App\Modules\Variant\Http\Resources\VariantResource;
use App\Modules\Variant\Services\VariantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class VariantController extends Controller
{
    public function __construct(
        private readonly VariantService $variants,
    ) {}

    public function index(ListVariantsRequest $request, Product $product): AnonymousResourceCollection
    {
        $paginator = $this->variants->listForProduct(
            product: $product,
            filters: $request->validated(),
            perPage: (int) $request->integer('per_page', 15),
        );

        return VariantResource::collection($paginator);
    }

    public function store(StoreVariantRequest $request, Product $product): JsonResponse
    {
        $variant = $this->variants->create($product, $request->validated());

        return (new VariantResource($variant))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Product $product, Variant $variant): VariantResource
    {
        return new VariantResource($variant);
    }

    public function update(UpdateVariantRequest $request, Product $product, Variant $variant): VariantResource
    {
        $updated = $this->variants->update($variant, $request->validated());

        return new VariantResource($updated);
    }

    public function destroy(Product $product, Variant $variant): JsonResponse
    {
        $this->variants->delete($variant);

        return response()->json(null, 204);
    }

    public function restore(Product $product, string $variant): VariantResource
    {
        $restored = $this->variants->restore($variant);

        if ($restored->product_id !== $product->id) {
            abort(404);
        }

        return new VariantResource($restored);
    }
}
