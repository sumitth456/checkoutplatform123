<?php

namespace App\Modules\Order\DTOs;

readonly class OrderAttributionDTO
{
    public function __construct(
        public ?int $funnelId = null,
        public ?int $funnelStepId = null,
        public ?int $campaignId = null,
        public ?int $platformCampaignId = null,
        public ?string $landingPageUrl = null,
        public ?int $landingPageId = null,
        public ?string $referrerUrl = null,
        public ?array $utmParameters = null,
        public ?array $landingPageData = null,
        public ?int $affiliateId = null,
        public ?int $affiliateClickId = null,
        public ?int $affiliateOfferId = null,
        public ?array $affiliateSubIds = null,
    ) {}

    public static function fromValidated(array $data): self
    {
        $utm = $data['utm'] ?? null;

        if (! is_array($utm)) {
            $utm = array_filter([
                'source' => $data['utm_source'] ?? null,
                'medium' => $data['utm_medium'] ?? null,
                'campaign' => $data['utm_campaign'] ?? null,
                'term' => $data['utm_term'] ?? null,
                'content' => $data['utm_content'] ?? null,
            ]);
        }

        return new self(
            funnelId: isset($data['funnel_id']) ? (int) $data['funnel_id'] : null,
            funnelStepId: isset($data['funnel_step_id']) ? (int) $data['funnel_step_id'] : null,
            campaignId: isset($data['campaign_id']) ? (int) $data['campaign_id'] : null,
            platformCampaignId: isset($data['platform_campaign_id']) ? (int) $data['platform_campaign_id'] : null,
            landingPageUrl: $data['landing_page_url'] ?? ($data['landing_page']['url'] ?? null),
            landingPageId: isset($data['landing_page_id']) ? (int) $data['landing_page_id'] : null,
            referrerUrl: $data['referrer_url'] ?? ($data['landing_page']['referrer'] ?? null),
            utmParameters: $utm ?: null,
            landingPageData: $data['landing_page_data'] ?? null,
            affiliateId: isset($data['affiliate_id']) ? (int) $data['affiliate_id'] : null,
            affiliateClickId: isset($data['affiliate_click_id']) ? (int) $data['affiliate_click_id'] : null,
            affiliateOfferId: isset($data['affiliate_offer_id']) ? (int) $data['affiliate_offer_id'] : null,
            affiliateSubIds: is_array($data['affiliate_sub_ids'] ?? null) ? $data['affiliate_sub_ids'] : null,
        );
    }

    public function toOrderAttributes(): array
    {
        return array_filter([
            'funnel_id' => $this->funnelId,
            'funnel_step_id' => $this->funnelStepId,
            'campaign_id' => $this->campaignId,
            'platform_campaign_id' => $this->platformCampaignId,
            'landing_page_url' => $this->landingPageUrl,
            'landing_page_id' => $this->landingPageId,
            'referrer_url' => $this->referrerUrl,
            'utm_parameters' => $this->utmParameters,
            'landing_page_data' => $this->landingPageData,
            'affiliate_id' => $this->affiliateId,
            'affiliate_click_id' => $this->affiliateClickId,
            'affiliate_offer_id' => $this->affiliateOfferId,
            'affiliate_sub_ids' => $this->affiliateSubIds,
        ], fn (mixed $value): bool => $value !== null);
    }
}
