<?php

namespace App\Modules\Analytics\Http\Resources;

use App\Modules\Analytics\Data\RevenueMetrics;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RevenueMetrics */
class RevenueMetricsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var RevenueMetrics $metrics */
        $metrics = $this->resource;

        return $metrics->toArray();
    }
}
