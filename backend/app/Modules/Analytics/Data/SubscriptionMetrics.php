<?php

namespace App\Modules\Analytics\Data;

readonly class SubscriptionMetrics
{
    /**
     * @param  array<string, int>  $byStatus
     */
    public function __construct(
        public int $totalSubscriptions,
        public int $newSubscriptions,
        public int $cancelledSubscriptions,
        public int $pastDueSubscriptions,
        public array $byStatus,
        public string $periodFrom,
        public string $periodTo,
    ) {}

    public function toArray(): array
    {
        return [
            'total_subscriptions' => $this->totalSubscriptions,
            'new_subscriptions' => $this->newSubscriptions,
            'cancelled_subscriptions' => $this->cancelledSubscriptions,
            'past_due_subscriptions' => $this->pastDueSubscriptions,
            'by_status' => $this->byStatus,
            'period' => [
                'from' => $this->periodFrom,
                'to' => $this->periodTo,
            ],
        ];
    }
}
