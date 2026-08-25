<?php

namespace App\Filament\Admin\Resources\Coupons\Pages;

use App\Filament\Admin\Resources\Coupons\Concerns\LogsCouponAdminActivity;
use App\Filament\Admin\Resources\Coupons\CouponResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCoupon extends ViewRecord
{
    use LogsCouponAdminActivity;

    protected static string $resource = CouponResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            DeleteAction::make()
                ->after(fn () => $this->logCouponActivity('deleted', "Coupon {$this->record->code} soft deleted")),
            ForceDeleteAction::make()
                ->after(fn () => $this->logCouponActivity('force_deleted', "Coupon {$this->record->code} permanently deleted")),
            RestoreAction::make()
                ->after(fn () => $this->logCouponActivity('restored', "Coupon {$this->record->code} restored")),
        ];
    }
}
