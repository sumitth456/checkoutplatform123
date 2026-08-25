<?php

namespace App\Modules\Analytics\Data;

readonly class MrrMetrics
{
    public function __construct(
        public string $mrr,
        public string $arr,
        public int $activeSubscriptions,
        public string $currency,
        public string $asOf,
    ) {}

    public function toArray(): array
    {
        return [
            'mrr' => $this->mrr,
            'arr' => $this->arr,
            'active_subscriptions' => $this->activeSubscriptions,
            'currency' => $this->currency,
            'as_of' => $this->asOf,
        ];
    }
}
