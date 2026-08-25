<?php

namespace App\Modules\Ach\DTOs;

use App\Modules\Ach\Enums\AchAccountType;
use App\Modules\Ach\Enums\AchHolderType;
use App\Modules\Ach\Enums\AchProviderType;

readonly class AchTokenizeResponse
{
    public function __construct(
        public string $paymentToken,
        public string $last4,
        public AchAccountType $accountType,
        public AchHolderType $holderType,
        public AchProviderType $provider,
        public ?string $authorizationId = null,
        public array $raw = [],
    ) {}
}
