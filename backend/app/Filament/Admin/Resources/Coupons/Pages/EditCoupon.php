<?php

namespace App\Filament\Admin\Resources\Coupons\Pages;

use App\Filament\Admin\Resources\Coupons\Concerns\LogsCouponAdminActivity;
use App\Filament\Admin\Resources\Coupons\CouponResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditCoupon extends EditRecord
{
    use LogsCouponAdminActivity;

    protected static string $resource = CouponResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->after(fn () => $this->logCouponActivity('deleted', "Coupon {$this->record->code} soft deleted")),
            ForceDeleteAction::make()
                ->after(fn () => $this->logCouponActivity('force_deleted', "Coupon {$this->record->code} permanently deleted")),
            RestoreAction::make()
                ->after(fn () => $this->logCouponActivity('restored', "Coupon {$this->record->code} restored")),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['code'])) {
            $data['code'] = strtoupper(trim((string) $data['code']));
        }

        if ((bool) ($data['applies_to_all_products'] ?? true)) {
            $data['products'] = [];
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $this->logCouponActivity('updated', "Coupon {$this->record->code} updated");
    }
}
