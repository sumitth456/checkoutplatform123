<?php

namespace App\Modules\Analytics\Http\Resources;

use App\Modules\Analytics\Data\RefundMetrics;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RefundMetrics */
class RefundMetricsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var RefundMetrics $metrics */
        $metrics = $this->resource;

        return $metrics->toArray();
    }
}
