<?php

namespace App\Modules\Subscription\Jobs;

use App\Modules\Admin\Services\PlatformConfigResolver;
use App\Modules\Subscription\Contracts\SubscriptionRepositoryInterface;
use App\Modules\Subscription\Services\RebillService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ChargeSubscriptionJob implements ShouldQueue
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
        RebillService $rebillService,
        PlatformConfigResolver $platformConfig,
    ): void {
        $lock = Cache::lock(
            "subscription:charge:{$this->subscriptionId}",
            $platformConfig->subscriptionChargeLockSeconds(),
        );

        if (! $lock->get()) {
            return;
        }

        try {
            DB::transaction(function () use ($subscriptions, $rebillService): void {
                $subscription = $subscriptions->lockForUpdate($this->subscriptionId);

                if ($subscription === null || ! $subscription->isBillable()) {
                    return;
                }

                if ($subscription->next_billing_at === null || $subscription->next_billing_at->isFuture()) {
                    return;
                }

                if (blank($subscription->payment_token)) {
                    throw new InvalidArgumentException("Subscription [{$subscription->uuid}] has no payment token.");
                }

                $rebillService->charge($subscription);
            });
        } finally {
            $lock->release();
        }
    }
}
