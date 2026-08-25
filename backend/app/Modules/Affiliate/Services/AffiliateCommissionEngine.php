<?php

namespace App\Modules\Affiliate\Services;

use App\Models\Affiliate;
use App\Models\AffiliateCampaignMapping;
use App\Models\AffiliateOffer;
use App\Models\Order;
use App\Modules\Affiliate\DTOs\ResolvedCommissionRule;
use App\Modules\Affiliate\Enums\CommissionType;
use App\Modules\Affiliate\Enums\RecurringCommissionMode;

class AffiliateCommissionEngine
{
    public function resolve(
        Affiliate $affiliate,
        Order $order,
        ?AffiliateOffer $offer = null,
        ?int $productId = null,
    ): ResolvedCommissionRule {
        if ($order->platform_campaign_id) {
            $mapping = AffiliateCampaignMapping::query()
                ->where('affiliate_id', $affiliate->id)
                ->where('platform_campaign_id', $order->platform_campaign_id)
                ->where('is_active', true)
                ->first();

            if ($mapping !== null && filled($mapping->commission_type) && $mapping->commission_value !== null) {
                return new ResolvedCommissionRule(
                    commissionType: $mapping->commission_type instanceof CommissionType
                        ? $mapping->commission_type->value
                        : (string) $mapping->commission_type,
                    commissionValue: (float) $mapping->commission_value,
                    ruleSource: 'campaign_override',
                    recurringMode: $offer?->recurring_commission_mode ?? $affiliate->recurring_commission_mode,
                    recurringRebillLimit: $offer?->recurring_commission_rebill_limit ?? $affiliate->recurring_commission_rebill_limit,
                );
            }
        }

        if ($offer !== null) {
            return new ResolvedCommissionRule(
                commissionType: $offer->commission_type->value,
                commissionValue: (float) $offer->commission_value,
                ruleSource: 'offer_override',
                recurringMode: $offer->recurring_commission_mode,
                recurringRebillLimit: $offer->recurring_commission_rebill_limit,
            );
        }

        return new ResolvedCommissionRule(
            commissionType: $affiliate->default_commission_type->value,
            commissionValue: (float) $affiliate->default_commission_value,
            ruleSource: 'affiliate_default',
            recurringMode: $affiliate->recurring_commission_mode,
            recurringRebillLimit: $affiliate->recurring_commission_rebill_limit,
        );
    }

    public function calculateAmount(ResolvedCommissionRule $rule, float $orderAmount): float
    {
        if ($rule->commissionType === CommissionType::Fixed->value) {
            return round($rule->commissionValue, 2);
        }

        return round($orderAmount * ($rule->commissionValue / 100), 2);
    }

    public function shouldCommissionRebill(
        ResolvedCommissionRule $rule,
        int $rebillNumber,
        bool $isInitialOrder,
    ): bool {
        if ($isInitialOrder) {
            return true;
        }

        $mode = $rule->recurringMode ?? RecurringCommissionMode::FirstOrderOnly;

        return match ($mode) {
            RecurringCommissionMode::FirstOrderOnly => false,
            RecurringCommissionMode::EveryRebill, RecurringCommissionMode::Lifetime => true,
            RecurringCommissionMode::FixedRebills => $rebillNumber <= (int) ($rule->recurringRebillLimit ?? 0),
        };
    }
}
