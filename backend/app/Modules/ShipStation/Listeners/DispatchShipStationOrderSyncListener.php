<?php

namespace App\Modules\ShipStation\Listeners;

use App\Modules\Order\Events\OrderPaid;
use App\Modules\ShipStation\Jobs\SyncOrderToShipStationJob;
use App\Modules\ShipStation\Services\ShipStationAutomaticProcessingHoldService;
use App\Modules\ShipStation\Services\ShipStationOrderSyncExclusionService;
use App\Modules\ShipStation\Services\ShipStationConfigResolver;
use App\Modules\ShipStation\Services\ShipStationOrderEligibilityService;
use Illuminate\Contracts\Queue\ShouldQueue;

class DispatchShipStationOrderSyncListener implements ShouldQueue
{
    public string $queue;

    public function __construct(
        private readonly ShipStationConfigResolver $config,
        private readonly ShipStationOrderEligibilityService $eligibility,
        private readonly ShipStationAutomaticProcessingHoldService $automaticProcessingHold,
        private readonly ShipStationOrderSyncExclusionService $syncExclusion,
    ) {
        $this->queue = (string) config('shipstation.queue', config('checkout.fulfillment_queue', 'default'));
    }

    public function handle(OrderPaid $event): void
    {
        if (! $this->config->isEnabled()) {
            return;
        }

        if ($event->order->isRebill()) {
            return;
        }

        if ($this->syncExclusion->isExcluded($event->order)) {
            return;
        }

        if ($this->automaticProcessingHold->holdsAutomaticProcessing($event->order)) {
            return;
        }

        $decision = $this->eligibility->evaluate($event->order);

        if (! $decision->eligible) {
            $this->eligibility->markSubmissionSkipped($event->order, $decision);

            return;
        }

        SyncOrderToShipStationJob::dispatch($event->order->id);
    }
}
