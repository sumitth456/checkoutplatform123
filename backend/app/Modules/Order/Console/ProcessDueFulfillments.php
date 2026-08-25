<?php

namespace App\Modules\Order\Console;

use App\Modules\Order\Services\DelayFulfillmentEngineService;
use Illuminate\Console\Command;

class ProcessDueFulfillments extends Command
{
    protected $signature = 'fulfillments:process-due';

    protected $description = 'Queue warehouse submissions for fulfillments whose ship date is today';

    public function handle(DelayFulfillmentEngineService $engine): int
    {
        $count = $engine->processDueFulfillments();

        $this->info("Queued {$count} fulfillment shipment job(s).");

        return self::SUCCESS;
    }
}
