<?php

namespace App\Modules\Analytics\Http\Resources;

use App\Modules\Analytics\Data\FailedPaymentMetrics;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FailedPaymentMetrics */
class FailedPaymentMetricsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var FailedPaymentMetrics $metrics */
        $metrics = $this->resource;

        return $metrics->toArray();
    }
}
