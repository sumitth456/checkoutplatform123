<?php

namespace App\Modules\Product\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Modules\Campaign\Services\CampaignVariantMatrixService;
use App\Models\CampaignProduct;
use Illuminate\Http\JsonResponse;

class ProductCheckoutVariantsController extends Controller
{
    public function __construct(
        private readonly CampaignVariantMatrixService $variantMatrix,
    ) {}

    public function __invoke(Product $product): JsonResponse
    {
        $line = new CampaignProduct([
            'product_id' => $product->id,
            'campaign_price' => null,
        ]);
        $line->setRelation('product', $product->load('variants'));

        $matrix = $this->variantMatrix->build($line);

        return response()->json(['data' => $matrix]);
    }
}
