<?php

namespace App\Modules\Order\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Order\Http\Requests\ValidateCouponRequest;
use App\Modules\Order\Services\CouponValidationService;
use Illuminate\Http\JsonResponse;

class CouponValidationController extends Controller
{
    public function __construct(
        private readonly CouponValidationService $coupons,
    ) {}

    public function __invoke(ValidateCouponRequest $request): JsonResponse
    {
        $subtotal = (float) $request->validated('subtotal', 0);

        $result = $this->coupons->validate(
            code: $request->validated('code'),
            subtotal: $subtotal,
        );

        return response()->json([
            'data' => [
                'valid' => true,
                'code' => $result['coupon']?->code,
                'discount_total' => number_format($result['discount_total'], 2, '.', ''),
                'message' => $result['message'],
            ],
        ]);
    }
}
