<?php

namespace App\Modules\Order\Actions;

use App\Modules\Order\DTOs\RequestContextDTO;
use App\Modules\Order\Enums\DeviceType;
use Illuminate\Http\Request;

class CaptureRequestContextAction
{
    public function execute(Request $request): RequestContextDTO
    {
        return new RequestContextDTO(
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            deviceType: $this->detectDeviceType($request->userAgent()),
        );
    }

    private function detectDeviceType(?string $userAgent): DeviceType
    {
        if ($userAgent === null) {
            return DeviceType::Unknown;
        }

        $ua = strtolower($userAgent);

        if (str_contains($ua, 'bot') || str_contains($ua, 'crawler')) {
            return DeviceType::Bot;
        }

        if (str_contains($ua, 'mobile') || str_contains($ua, 'iphone') || str_contains($ua, 'android')) {
            return DeviceType::Mobile;
        }

        if (str_contains($ua, 'ipad') || str_contains($ua, 'tablet')) {
            return DeviceType::Tablet;
        }

        return DeviceType::Desktop;
    }
}
