<?php

namespace App\Filament\Admin\Resources\Orders\Pages;

use App\Filament\Admin\Resources\Orders\Concerns\LogsOrderAdminActivity;
use App\Filament\Admin\Resources\Orders\OrderResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditOrder extends EditRecord
{
    use LogsOrderAdminActivity;

    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->after(fn () => $this->logOrderActivity('deleted', "Order {$this->record->order_number} soft deleted")),
            ForceDeleteAction::make()
                ->after(fn () => $this->logOrderActivity('force_deleted', "Order {$this->record->order_number} permanently deleted")),
            RestoreAction::make()
                ->after(fn () => $this->logOrderActivity('restored', "Order {$this->record->order_number} restored")),
        ];
    }

    protected function afterSave(): void
    {
        $this->logOrderActivity('updated', "Order {$this->record->order_number} updated");
    }
}
