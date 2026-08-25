<?php

namespace App\Modules\Analytics\Http\Resources;

use App\Modules\Analytics\Data\SubscriptionMetrics;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SubscriptionMetrics */
class SubscriptionMetricsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var SubscriptionMetrics $metrics */
        $metrics = $this->resource;

        return $metrics->toArray();
    }
}
