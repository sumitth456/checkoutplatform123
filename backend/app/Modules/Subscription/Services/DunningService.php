<?php

namespace App\Modules\Subscription\Services;

use App\Models\Subscription;

class DunningService
{
    public function reset(Subscription $subscription): void
    {
        $subscription->dunning_stage = 0;
        $subscription->next_dunning_at = null;
    }

    public function scheduleNextStage(Subscription $subscription): void
    {
        // Dunning stages are processed by subscriptions:process-dunning.
    }
}
