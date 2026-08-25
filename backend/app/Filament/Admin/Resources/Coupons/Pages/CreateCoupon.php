<?php

namespace App\Filament\Admin\Resources\Coupons\Pages;

use App\Filament\Admin\Resources\Coupons\Concerns\LogsCouponAdminActivity;
use App\Filament\Admin\Resources\Coupons\CouponResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCoupon extends CreateRecord
{
    use LogsCouponAdminActivity;

    protected static string $resource = CouponResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (isset($data['code'])) {
            $data['code'] = strtoupper(trim((string) $data['code']));
        }

        if ((bool) ($data['applies_to_all_products'] ?? true)) {
            $data['products'] = [];
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->logCouponActivity('created', "Coupon {$this->record->code} created");
    }
}
