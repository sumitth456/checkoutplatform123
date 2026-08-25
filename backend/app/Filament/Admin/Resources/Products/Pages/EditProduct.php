<?php

namespace App\Filament\Admin\Resources\Products\Pages;

use App\Filament\Admin\Resources\Products\Concerns\LogsProductAdminActivity;
use App\Filament\Admin\Resources\Products\ProductResource;
use App\Modules\Product\Enums\ProductType;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    use LogsProductAdminActivity;

    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->after(fn () => $this->logProductActivity('deleted', "Product {$this->record->name} soft deleted")),
            ForceDeleteAction::make()
                ->after(fn () => $this->logProductActivity('force_deleted', "Product {$this->record->name} permanently deleted")),
            RestoreAction::make()
                ->after(fn () => $this->logProductActivity('restored', "Product {$this->record->name} restored")),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->normalizeProductData($data);
    }

    protected function afterSave(): void
    {
        $this->logProductActivity('updated', "Product {$this->record->name} updated");
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizeProductData(array $data): array
    {
        $data['is_subscription'] = ($data['type'] ?? '') === ProductType::Subscription->value
            || filled($data['subscription_plan_id'] ?? null);

        if (($data['status'] ?? '') === 'active' && blank($data['published_at'] ?? null)) {
            $data['published_at'] = now();
        }

        return $data;
    }
}
