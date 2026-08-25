<?php

namespace App\Modules\Campaign\Observers;

use App\Models\Campaign;
use App\Modules\Campaign\Services\SubscriptionPlanCampaignBillingService;

final class CampaignSubscriptionPlanObserver
{
    public function __construct(
        private readonly SubscriptionPlanCampaignBillingService $planBilling,
    ) {}

    public function updated(Campaign $campaign): void
    {
        if (! $campaign->wasChanged('subscription_plan_id')) {
            return;
        }

        if (! $this->planBilling->campaignUsesPlan($campaign)) {
            return;
        }

        $this->planBilling->applyPlanToCampaign($campaign->fresh(['subscriptionPlan.steps', 'campaignProducts']));
    }
}
