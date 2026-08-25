<?php

namespace App\Filament\Admin\Resources\Products\Pages;

use App\Filament\Admin\Resources\Products\Concerns\LogsProductAdminActivity;
use App\Filament\Admin\Resources\Products\ProductResource;
use App\Modules\Product\Enums\ProductType;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    use LogsProductAdminActivity;

    protected static string $resource = ProductResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->normalizeProductData($data);
    }

    protected function afterCreate(): void
    {
        $this->logProductActivity('created', "Product {$this->record->name} created");
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
