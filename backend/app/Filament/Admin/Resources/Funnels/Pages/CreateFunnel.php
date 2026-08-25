<?php

namespace App\Filament\Admin\Resources\Funnels\Pages;

use App\Filament\Admin\Resources\Funnels\Concerns\LogsFunnelAdminActivity;
use App\Filament\Admin\Resources\Funnels\FunnelResource;
use App\Modules\Order\Enums\FunnelStatus;
use Filament\Resources\Pages\CreateRecord;

class CreateFunnel extends CreateRecord
{
    use LogsFunnelAdminActivity;

    protected static string $resource = FunnelResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->normalizeFunnelData($data);
    }

    protected function afterCreate(): void
    {
        $this->logFunnelActivity('created', "Funnel {$this->record->name} created");
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizeFunnelData(array $data): array
    {
        if (($data['status'] ?? '') === FunnelStatus::Active->value && blank($data['published_at'] ?? null)) {
            $data['published_at'] = now();
        }

        $data['settings'] = array_filter((array) ($data['settings'] ?? []), fn (mixed $value): bool => $value !== null && $value !== '');

        return $data;
    }
}
