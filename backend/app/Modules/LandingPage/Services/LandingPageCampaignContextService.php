<?php

namespace App\Modules\LandingPage\Services;

use App\Models\LandingPage;
use App\Modules\Campaign\Services\CampaignCheckoutContextService;
use InvalidArgumentException;

final class LandingPageCampaignContextService
{
    public function __construct(
        private readonly CampaignCheckoutContextService $campaignCheckout,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function buildCheckoutContext(LandingPage $page): array
    {
        $page->loadMissing('campaign');

        $campaign = $page->campaign;

        if ($campaign === null) {
            throw new InvalidArgumentException('Landing page is not linked to a campaign.');
        }

        $context = $this->campaignCheckout->build($campaign);

        return array_merge($context, [
            'landing_page_uuid' => $page->uuid,
            'landing_page_slug' => $page->slug,
            'checkout_api_url' => url('/api/v1/checkout'),
            'payment_intent_api_url' => url('/api/v1/checkout/payment-intent'),
        ]);
    }
}
