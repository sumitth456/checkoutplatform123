<?php

namespace App\Filament\Admin\Resources\Coupons\Concerns;

use App\Models\Coupon;
use App\Support\Admin\AdminActivityLogger;

trait LogsCouponAdminActivity
{
    protected function logCouponActivity(string $event, ?string $description = null, array $properties = []): void
    {
        if (! $this->record instanceof Coupon) {
            return;
        }

        AdminActivityLogger::log(
            module: 'coupons',
            event: $event,
            description: $description,
            subject: $this->record,
            properties: $properties,
        );
    }
}
