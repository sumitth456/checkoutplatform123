<?php

namespace App\Modules\Analytics\Http\Resources;

use App\Modules\Analytics\Data\AnalyticsOverview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AnalyticsOverview */
class AnalyticsOverviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var AnalyticsOverview $overview */
        $overview = $this->resource;

        return $overview->toArray();
    }
}
