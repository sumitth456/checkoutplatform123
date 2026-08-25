<?php

namespace App\Modules\Affiliate\DTOs;

use App\Modules\Affiliate\Enums\RecurringCommissionMode;

readonly class ResolvedCommissionRule
{
    public function __construct(
        public string $commissionType,
        public float $commissionValue,
        public string $ruleSource,
        public ?RecurringCommissionMode $recurringMode = null,
        public ?int $recurringRebillLimit = null,
    ) {}
}
