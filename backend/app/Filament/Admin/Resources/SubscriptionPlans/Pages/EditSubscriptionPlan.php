<?php

namespace App\Filament\Admin\Resources\SubscriptionPlans\Pages;

use App\Filament\Admin\Resources\SubscriptionPlans\SubscriptionPlanResource;
use App\Modules\Campaign\Services\SubscriptionPlanCampaignBillingService;
use Filament\Resources\Pages\EditRecord;

class EditSubscriptionPlan extends EditRecord
{
    protected static string $resource = SubscriptionPlanResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['linked_campaign_id'] = app(SubscriptionPlanCampaignBillingService::class)
            ->linkedCampaignId($this->record);

        return $data;
    }

    protected function afterSave(): void
    {
        $campaignId = $this->form->getState()['linked_campaign_id'] ?? null;

        app(SubscriptionPlanCampaignBillingService::class)->assignPlanToCampaign(
            $this->record,
            filled($campaignId) ? (int) $campaignId : null,
        );
    }
}
