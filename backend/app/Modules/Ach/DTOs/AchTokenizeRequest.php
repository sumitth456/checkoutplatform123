<?php

namespace App\Modules\Ach\DTOs;

use App\Modules\Ach\Enums\AchAccountType;
use App\Modules\Ach\Enums\AchHolderType;

readonly class AchTokenizeRequest
{
    public function __construct(
        public string $accountHolderName,
        public string $routingNumber,
        public string $accountNumber,
        public AchAccountType $accountType,
        public AchHolderType $holderType,
        public ?string $bankName = null,
        public ?int $customerId = null,
        public ?string $idempotencyKey = null,
    ) {}
}
