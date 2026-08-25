<?php

namespace App\Modules\Admin\Services;

use App\Modules\Admin\Data\DashboardFilter;
use App\Modules\Order\Enums\FulfillmentRecordStatus;
use App\Modules\Order\Enums\OrderPaymentStatus;
use App\Modules\Order\Enums\OrderType;
use App\Modules\Subscription\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class DashboardFilterApplicator
{
    public function __construct(
        private readonly GlobalDashboardFilterService $filterService,
    ) {}

    public function resolveFromRequest(Request $request): DashboardFilter
    {
        return $this->filterService->resolve(
            quickRange: $request->query('df_range') ?? $request->query('quick_range'),
            from: $request->query('df_from'),
            to: $request->query('df_to'),
            currency: $request->query('df_currency'),
            campaignId: $request->integer('df_campaign') ?: null,
            campaignCategory: $request->query('df_category'),
            trafficSource: $request->query('df_traffic'),
            affiliateId: $request->integer('df_affiliate') ?: null,
            paymentGatewayId: $request->integer('df_gateway') ?: null,
            fulfillmentHouseId: $request->integer('df_fhouse') ?: null,
        );
    }

    /** @param  Builder<\App\Models\Order>  $query */
    public function applyToOrders(Builder $query, Request $request): Builder
    {
        if ($request->query('df_range') === 'all_time' || $request->query('quick_range') === 'all_time') {
            return $this->applyOrderExtras($query, $request);
        }

        if (! $this->hasDashboardParams($request)) {
            return $query;
        }

        $filter = $this->resolveFromRequest($request);

        if (! $this->shouldApplyDateFilter($request)) {
            $query
                ->when($filter->currency, fn (Builder $q) => $q->where('currency', $filter->currency))
                ->when($filter->campaignId, fn (Builder $q) => $q->where('platform_campaign_id', $filter->campaignId))
                ->when($filter->affiliateId, fn (Builder $q) => $q->where('affiliate_id', $filter->affiliateId))
                ->when($filter->paymentGatewayId, fn (Builder $q) => $q->where('payment_gateway_id', $filter->paymentGatewayId))
                ->when($filter->trafficSource, fn (Builder $q) => $q->where('metadata->utm_source', $filter->trafficSource))
                ->when($filter->campaignCategory, fn (Builder $q) => $q->whereHas('platformCampaign', fn (Builder $c) => $c->where('metadata->category', $filter->campaignCategory)));

            return $this->applyOrderExtras($query, $request);
        }

        $query
            ->whereBetween('placed_at', [$filter->from, $filter->to])
            ->when($filter->currency, fn (Builder $q) => $q->where('currency', $filter->currency))
            ->when($filter->campaignId, fn (Builder $q) => $q->where('platform_campaign_id', $filter->campaignId))
            ->when($filter->affiliateId, fn (Builder $q) => $q->where('affiliate_id', $filter->affiliateId))
            ->when($filter->paymentGatewayId, fn (Builder $q) => $q->where('payment_gateway_id', $filter->paymentGatewayId))
            ->when($filter->trafficSource, fn (Builder $q) => $q->where('metadata->utm_source', $filter->trafficSource))
            ->when($filter->campaignCategory, fn (Builder $q) => $q->whereHas('platformCampaign', fn (Builder $c) => $c->where('metadata->category', $filter->campaignCategory)));

        return $this->applyOrderExtras($query, $request);
    }

    /** @param  Builder<\App\Models\Subscription>  $query */
    public function applyToSubscriptions(Builder $query, Request $request): Builder
    {
        if (! $this->hasDashboardParams($request)) {
            return $query;
        }

        $filter = $this->resolveFromRequest($request);

        $query
            ->when($request->filled('df_from') || $request->filled('df_range'), fn (Builder $q) => $q->whereBetween('created_at', [$filter->from, $filter->to]))
            ->when($filter->campaignId, fn (Builder $q) => $q->where('platform_campaign_id', $filter->campaignId));

        if ($status = $request->query('df_status')) {
            $query->where('status', match ($status) {
                'active' => SubscriptionStatus::Active,
                'paused' => SubscriptionStatus::Paused,
                'cancelled' => SubscriptionStatus::Cancelled,
                'past_due' => SubscriptionStatus::PastDue,
                default => $status,
            });
        }

        return $query;
    }

    /** @param  Builder<\App\Models\Fulfillment>  $query */
    public function applyToFulfillments(Builder $query, Request $request): Builder
    {
        if (! $this->hasDashboardParams($request)) {
            return $query;
        }

        $filter = $this->resolveFromRequest($request);

        $query
            ->whereBetween('created_at', [$filter->from, $filter->to])
            ->when($filter->fulfillmentHouseId, fn (Builder $q) => $q->where('fulfillment_house_id', $filter->fulfillmentHouseId));

        if ($status = $request->query('df_status')) {
            $statusEnum = match ($status) {
                'pending' => [FulfillmentRecordStatus::Pending, FulfillmentRecordStatus::Scheduled, FulfillmentRecordStatus::Queued],
                'submitted' => [FulfillmentRecordStatus::Submitted],
                'packed' => [FulfillmentRecordStatus::Packed],
                'shipped' => [FulfillmentRecordStatus::Shipped, FulfillmentRecordStatus::InTransit],
                'delivered' => [FulfillmentRecordStatus::Delivered],
                'returned' => [FulfillmentRecordStatus::Returned],
                'cancelled' => [FulfillmentRecordStatus::Cancelled],
                'failed' => [FulfillmentRecordStatus::Failed],
                'rma_requested' => [FulfillmentRecordStatus::RmaRequested],
                default => null,
            };

            if (is_array($statusEnum)) {
                $query->whereIn('status', $statusEnum);
            }
        }

        return $query;
    }

    /** @param  Builder<\App\Models\Transaction>  $query */
    public function applyToTransactions(Builder $query, Request $request): Builder
    {
        if (! $this->hasDashboardParams($request)) {
            return $query;
        }

        $filter = $this->resolveFromRequest($request);

        $query
            ->whereBetween('processed_at', [$filter->from, $filter->to])
            ->when($filter->paymentGatewayId ?: $request->integer('df_gateway') ?: null, fn (Builder $q, int $id) => $q->where('payment_gateway_id', $id))
            ->when($filter->currency, fn (Builder $q) => $q->where('currency', $filter->currency));

        if ($type = $request->query('df_type')) {
            $query->where('type', $type);
        }

        return $query;
    }

    /** @param  Builder<\App\Models\Subscription>  $query */
    public function applyToRebillQueue(Builder $query, Request $request): Builder
    {
        if (! $this->hasDashboardParams($request)) {
            return $query;
        }

        $filter = $this->resolveFromRequest($request);

        if ($filter->campaignId) {
            $query->where('platform_campaign_id', $filter->campaignId);
        }

        if ($request->boolean('df_upcoming')) {
            return $query->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::PastDue])
                ->whereBetween('next_billing_at', [now(), now()->addDays(7)]);
        }

        if ($request->boolean('df_failed')) {
            return $query->whereHas('orders', fn (Builder $q) => $q
                ->where('order_type', OrderType::Rebill)
                ->where('payment_status', OrderPaymentStatus::Failed)
                ->whereBetween('placed_at', [$filter->from, $filter->to]));
        }

        if ($request->boolean('df_retry')) {
            return $query->where('status', SubscriptionStatus::PastDue);
        }

        if ($status = $request->query('df_status')) {
            $query->where('status', match ($status) {
                'active' => SubscriptionStatus::Active,
                'paused' => SubscriptionStatus::Paused,
                'cancelled' => SubscriptionStatus::Cancelled,
                default => $status,
            });
        }

        if ($request->filled('df_from') || $request->filled('df_range')) {
            $query->whereBetween('created_at', [$filter->from, $filter->to]);
        }

        return $query;
    }

    /** @param  Builder<\App\Models\Order>  $query */
    private function applyOrderExtras(Builder $query, Request $request): Builder
    {
        if ($payment = $request->query('df_payment')) {
            $query->where('payment_status', match ($payment) {
                'paid' => OrderPaymentStatus::Paid,
                'failed' => OrderPaymentStatus::Failed,
                'pending' => OrderPaymentStatus::Pending,
                'refunded' => OrderPaymentStatus::Refunded,
                'partially_refunded' => OrderPaymentStatus::PartiallyRefunded,
                default => $payment,
            });
        }

        if ($request->boolean('df_upsell')) {
            $query->where('metadata->upsell', true);
        }

        if ($request->boolean('df_bump')) {
            $query->where('metadata->order_bump', true);
        }

        if ($request->boolean('df_subscription')) {
            $query->whereNotNull('source_subscription_id')->where('order_type', OrderType::Initial);
        }

        if ($request->query('tab') === 'rebill') {
            $query->where('order_type', OrderType::Rebill);
        }

        return $query;
    }

    private function shouldApplyDateFilter(Request $request): bool
    {
        if ($request->query('df_range') === 'all_time' || $request->query('quick_range') === 'all_time') {
            return false;
        }

        return $request->filled('df_from') || $request->filled('df_range') || $request->filled('quick_range');
    }

    private function hasDashboardParams(Request $request): bool
    {
        return $request->hasAny([
            'df_from', 'df_to', 'df_range', 'quick_range', 'df_campaign', 'df_category',
            'df_traffic', 'df_affiliate', 'df_gateway', 'df_fhouse',
            'df_payment', 'df_status', 'df_type', 'df_upsell', 'df_bump',
            'df_subscription', 'df_upcoming', 'df_failed', 'df_retry', 'tab',
        ]);
    }
}
