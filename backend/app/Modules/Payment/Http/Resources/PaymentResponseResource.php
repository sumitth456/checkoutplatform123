<?php

namespace App\Modules\Payment\Http\Resources;

use App\Modules\Payment\DTOs\PaymentResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PaymentResponse */
class PaymentResponseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var PaymentResponse $response */
        $response = $this->resource;

        return $response->toArray();
    }
}
