<?php

namespace App\Modules\Analytics\Http\Resources;

use App\Modules\Analytics\Data\MrrMetrics;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MrrMetrics */
class MrrMetricsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var MrrMetrics $metrics */
        $metrics = $this->resource;

        return $metrics->toArray();
    }
}
