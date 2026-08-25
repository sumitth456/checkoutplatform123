<?php

namespace App\Modules\Admin\Services\Timeline;

use App\Models\Order;
use Illuminate\Database\Eloquent\Model;

final class EntityTimelineResolver
{
    public function __construct(
        private readonly OrderEntityTimelineService $orders,
        private readonly CustomerEntityTimelineService $customers,
        private readonly SubscriptionEntityTimelineService $subscriptions,
        private readonly TransactionEntityTimelineService $transactions,
        private readonly GenericEntityTimelineService $generic,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function present(Model $record): array
    {
        return match (true) {
            $record instanceof Order => $this->orders->present($record),
            $record instanceof \App\Models\Customer => $this->customers->present($record),
            $record instanceof \App\Models\Subscription => $this->subscriptions->present($record),
            $record instanceof \App\Models\Transaction => $this->transactions->present($record),
            default => $this->generic->present($record),
        };
    }
}
