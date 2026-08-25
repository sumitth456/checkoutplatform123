<?php

namespace App\Filament\Admin\Support\Concerns;

use App\Modules\Admin\Services\DashboardFilterApplicator;
use Illuminate\Database\Eloquent\Builder;

trait AppliesDashboardFilters
{
    /** @param  Builder<\Illuminate\Database\Eloquent\Model>  $query */
    protected function applyDashboardFilters(Builder $query, string $context): Builder
    {
        return match ($context) {
            'orders' => app(DashboardFilterApplicator::class)->applyToOrders($query, request()),
            'subscriptions' => app(DashboardFilterApplicator::class)->applyToSubscriptions($query, request()),
            'fulfillments' => app(DashboardFilterApplicator::class)->applyToFulfillments($query, request()),
            'transactions' => app(DashboardFilterApplicator::class)->applyToTransactions($query, request()),
            'rebill_queue' => app(DashboardFilterApplicator::class)->applyToRebillQueue($query, request()),
            default => $query,
        };
    }
}
