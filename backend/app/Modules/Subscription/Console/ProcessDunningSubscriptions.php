<?php

namespace App\Modules\Subscription\Console;

use App\Modules\Subscription\Services\SubscriptionEngineService;
use Illuminate\Console\Command;

class ProcessDunningSubscriptions extends Command
{
    protected $signature = 'subscriptions:process-dunning';

    protected $description = 'Queue dunning stage jobs for subscriptions with due dunning reminders';

    public function handle(SubscriptionEngineService $engine): int
    {
        $count = $engine->processDueDunning();

        $this->info("Queued {$count} dunning job(s).");

        return self::SUCCESS;
    }
}
