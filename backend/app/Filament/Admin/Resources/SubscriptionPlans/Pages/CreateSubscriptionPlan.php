<?php

namespace App\Filament\Admin\Resources\SubscriptionPlans\Pages;

use App\Filament\Admin\Resources\SubscriptionPlans\SubscriptionPlanResource;
use App\Modules\Campaign\Services\SubscriptionPlanCampaignBillingService;
use Filament\Resources\Pages\CreateRecord;

class CreateSubscriptionPlan extends CreateRecord
{
    protected static string $resource = SubscriptionPlanResource::class;

    protected function afterCreate(): void
    {
        $campaignId = $this->form->getState()['linked_campaign_id'] ?? null;

        if (blank($campaignId)) {
            return;
        }

        app(SubscriptionPlanCampaignBillingService::class)
            ->assignPlanToCampaign($this->record, (int) $campaignId);
    }
}
