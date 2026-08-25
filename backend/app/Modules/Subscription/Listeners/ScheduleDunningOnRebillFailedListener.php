<?php

namespace App\Modules\Subscription\Listeners;

use App\Modules\Subscription\Events\SubscriptionRebillFailed;
use App\Modules\Subscription\Services\DunningService;
use Illuminate\Contracts\Queue\ShouldQueue;

class ScheduleDunningOnRebillFailedListener implements ShouldQueue
{
    public string $queue;

    public function __construct(
        private readonly DunningService $dunning,
    ) {
        $this->queue = (string) config('subscription.queues.dunning', 'default');
    }

    public function handle(SubscriptionRebillFailed $event): void
    {
        $this->dunning->schedule($event->subscription->fresh(['plan.dunningPolicy']));
    }
}
