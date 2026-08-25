<?php

namespace App\Modules\Affiliate\Services;

use App\Models\Affiliate;
use App\Models\AffiliateClick;
use App\Models\AffiliateCommission;
use App\Models\AffiliateConversion;
use App\Models\AffiliateOffer;
use App\Models\Coupon;
use App\Models\Order;
use App\Modules\Affiliate\Enums\AffiliateStatus;
use App\Modules\Affiliate\Enums\CommissionStatus;
use App\Modules\Affiliate\Enums\CommissionType;
use App\Modules\Order\Enums\OrderType;
use Illuminate\Support\Facades\Http;

class AffiliateConversionService
{
    public function __construct(
        private readonly AffiliateCommissionEngine $commissionEngine,
    ) {}

    public function recordOrderConversion(Order $order): ?AffiliateConversion
    {
        $affiliate = $this->resolveAffiliateForOrder($order->loadMissing('coupon'));

        if ($affiliate === null || $affiliate->status !== AffiliateStatus::Active) {
            return null;
        }

        $isRebill = $order->order_type === OrderType::Rebill;
        $rebillNumber = (int) ($order->rebill_number ?? 0);

        $offer = $order->affiliate_offer_id
            ? AffiliateOffer::query()->find($order->affiliate_offer_id)
            : null;

        $rule = $this->commissionEngine->resolve($affiliate, $order, $offer);

        if (! $this->commissionEngine->shouldCommissionRebill($rule, $rebillNumber, ! $isRebill)) {
            return null;
        }

        $revenue = (float) $order->grand_total;
        $commissionAmount = $this->commissionEngine->calculateAmount($rule, $revenue);

        $click = $order->affiliate_click_id
            ? AffiliateClick::query()->find($order->affiliate_click_id)
            : null;

        $conversionType = $isRebill ? 'rebill' : 'sale';

        $existing = AffiliateConversion::query()
            ->where('order_id', $order->id)
            ->where('affiliate_id', $affiliate->id)
            ->where('conversion_type', $conversionType)
            ->where('rebill_number', $isRebill ? $rebillNumber : null)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $conversion = AffiliateConversion::query()->create([
            'affiliate_id' => $affiliate->id,
            'offer_id' => $offer?->id,
            'platform_campaign_id' => $order->platform_campaign_id,
            'click_id' => $click?->id,
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'subscription_id' => $order->source_subscription_id,
            'conversion_type' => $conversionType,
            'revenue' => $revenue,
            'commission_amount' => $commissionAmount,
            'currency' => $order->currency,
            'sub_id_1' => $click?->sub_id_1,
            'sub_id_2' => $click?->sub_id_2,
            'sub_id_3' => $click?->sub_id_3,
            'sub_id_4' => $click?->sub_id_4,
            'sub_id_5' => $click?->sub_id_5,
            'rebill_number' => $isRebill ? $rebillNumber : null,
            'product_ids' => $order->items()->pluck('product_id')->filter()->values()->all(),
            'converted_at' => $order->paid_at ?? $order->placed_at ?? now(),
        ]);

        AffiliateCommission::query()->create([
            'affiliate_id' => $affiliate->id,
            'conversion_id' => $conversion->id,
            'order_id' => $order->id,
            'status' => CommissionStatus::Pending,
            'commission_type' => $rule->commissionType,
            'commission_rate' => $rule->commissionValue,
            'order_amount' => $revenue,
            'commission_amount' => $commissionAmount,
            'currency' => $order->currency,
            'rule_source' => $rule->ruleSource,
            'rebill_number' => $isRebill ? $rebillNumber : null,
            'earned_at' => $conversion->converted_at,
        ]);

        $affiliate->increment('total_conversions');
        $affiliate->increment('lifetime_revenue', $revenue);
        $affiliate->increment('pending_commission', $commissionAmount);

        if ($click?->tracking_link_id) {
            $click->trackingLink?->increment('conversion_count');
        }

        $this->firePostback($affiliate, $conversion);

        return $conversion;
    }

    public function resolveAffiliateForOrder(Order $order): ?Affiliate
    {
        if ($order->affiliate_id) {
            return Affiliate::query()->find($order->affiliate_id);
        }

        if ($order->coupon_id && $order->coupon?->affiliate_id) {
            return Affiliate::query()->find($order->coupon->affiliate_id);
        }

        if ($isRebill = $order->order_type === OrderType::Rebill) {
            $parent = $order->parentOrder;

            if ($parent?->affiliate_id) {
                return Affiliate::query()->find($parent->affiliate_id);
            }
        }

        return null;
    }

    public function resolveAffiliateFromCoupon(?string $couponCode): ?Affiliate
    {
        if (blank($couponCode)) {
            return null;
        }

        $coupon = Coupon::query()->where('code', $couponCode)->first();

        if ($coupon?->affiliate_id === null) {
            return null;
        }

        $affiliate = Affiliate::query()->find($coupon->affiliate_id);

        return $affiliate?->allow_coupon_attribution ? $affiliate : null;
    }

    private function firePostback(Affiliate $affiliate, AffiliateConversion $conversion): void
    {
        if (blank($affiliate->postback_url)) {
            return;
        }

        try {
            Http::timeout(5)->get($affiliate->postback_url, [
                'affiliate_id' => $affiliate->tracking_id,
                'conversion_id' => $conversion->uuid,
                'order_id' => $conversion->order_id,
                'revenue' => $conversion->revenue,
                'commission' => $conversion->commission_amount,
                'currency' => $conversion->currency,
            ]);
        } catch (\Throwable) {
            // Postback failures should not block order processing.
        }
    }
}
