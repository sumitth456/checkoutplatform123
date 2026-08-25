<?php

namespace App\Modules\Subscription\Console;

use App\Modules\Subscription\Contracts\SubscriptionRepositoryInterface;
use App\Modules\Subscription\Jobs\ChargeSubscriptionJob;
use Illuminate\Console\Command;

class ProcessDueSubscriptions extends Command
{
    protected $signature = 'subscriptions:process-due';

    protected $description = 'Queue charge jobs for subscriptions due for billing';

    public function handle(SubscriptionRepositoryInterface $subscriptions): int
    {
        $due = $subscriptions->findDueForBilling();
        $count = 0;

        foreach ($due as $subscription) {
            ChargeSubscriptionJob::dispatch($subscription->id)
                ->onQueue((string) config('subscription.queues.billing', 'default'));
            $count++;
        }

        $this->info("Queued {$count} subscription charge job(s).");

        return self::SUCCESS;
    }
}
