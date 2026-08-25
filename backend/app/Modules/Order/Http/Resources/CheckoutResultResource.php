<?php

namespace App\Modules\Order\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
class CheckoutResultResource extends JsonResource
{
    public function __construct(
        Order $resource,
        private readonly ?array $payment = null,
    ) {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'order' => (new OrderResource($this->resource))->toArray($request),
            'payment' => $this->payment,
        ];
    }
}
