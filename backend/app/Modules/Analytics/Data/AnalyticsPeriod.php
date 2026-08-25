<?php

namespace App\Modules\Analytics\Data;

use Carbon\Carbon;
use Carbon\CarbonInterface;

readonly class AnalyticsPeriod
{
    public function __construct(
        public CarbonInterface $from,
        public CarbonInterface $to,
        public ?string $currency = null,
    ) {}

    public static function fromRequest(?string $from, ?string $to, ?string $currency = null): self
    {
        return new self(
            from: $from ? Carbon::parse($from) : now()->startOfMonth(),
            to: $to ? Carbon::parse($to) : now()->endOfDay(),
            currency: $currency ? strtoupper($currency) : null,
        );
    }
}
