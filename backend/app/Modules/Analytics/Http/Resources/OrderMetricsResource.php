<?php

namespace App\Modules\Analytics\Http\Resources;

use App\Modules\Analytics\Data\OrderMetrics;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OrderMetrics */
class OrderMetricsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var OrderMetrics $metrics */
        $metrics = $this->resource;

        return $metrics->toArray();
    }
}
