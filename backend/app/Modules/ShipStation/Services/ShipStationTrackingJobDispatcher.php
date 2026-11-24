<?php

namespace App\Modules\ShipStation\Services;

use App\Modules\ShipStation\Jobs\SyncShipStationTrackingJob;

class ShipStationTrackingJobDispatcher
{
    public function dispatchForReceipt(int $receiptId): void
    {
        SyncShipStationTrackingJob::dispatch($receiptId);
    }
}