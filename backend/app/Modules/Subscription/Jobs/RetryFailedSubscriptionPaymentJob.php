<?php

namespace App\Modules\Subscription\Jobs;

use App\Modules\Subscription\Contracts\SubscriptionRepositoryInterface;
use App\Modules\Subscription\Services\FailedPaymentRetryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RetryFailedSubscriptionPaymentJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly int $subscriptionId,
    ) {}

    public function handle(
        SubscriptionRepositoryInterface $subscriptions,
        FailedPaymentRetryService $retryService,
    ): void {
        $subscription = $subscriptions->findById($this->subscriptionId);

        if ($subscription === null) {
            return;
        }

        $retryService->retryNow($subscription);
    }
}
