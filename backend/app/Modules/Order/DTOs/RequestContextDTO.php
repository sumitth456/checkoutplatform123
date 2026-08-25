<?php

namespace App\Modules\Order\DTOs;

use App\Modules\Order\Enums\DeviceType;

readonly class RequestContextDTO
{
    public function __construct(
        public ?string $ipAddress,
        public ?string $userAgent,
        public DeviceType $deviceType,
    ) {}

    public function toOrderAttributes(): array
    {
        return [
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'device_type' => $this->deviceType->value,
        ];
    }
}
