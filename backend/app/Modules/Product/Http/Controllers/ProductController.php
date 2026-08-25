<?php

namespace App\Modules\Product\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Modules\Product\Http\Requests\ListProductsRequest;
use App\Modules\Product\Http\Requests\StoreProductRequest;
use App\Modules\Product\Http\Requests\UpdateProductRequest;
use App\Modules\Product\Http\Resources\ProductResource;
use App\Modules\Product\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductService $products,
    ) {}

    public function index(ListProductsRequest $request): AnonymousResourceCollection
    {
        $paginator = $this->products->list(
            filters: $request->validated(),
            perPage: (int) $request->integer('per_page', 15),
        );

        return ProductResource::collection($paginator);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = $this->products->create($request->validated());

        return (new ProductResource($product->load('variants')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Product $product): ProductResource
    {
        return new ProductResource($product->load('variants'));
    }

    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        $updated = $this->products->update($product, $request->validated());

        return new ProductResource($updated);
    }

    public function destroy(Product $product): JsonResponse
    {
        $this->products->delete($product);

        return response()->json(null, 204);
    }

    public function restore(Product $product): ProductResource
    {
        $restored = $this->products->restore($product->uuid);

        return new ProductResource($restored);
    }
}
