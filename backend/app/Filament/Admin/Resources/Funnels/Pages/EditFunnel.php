<?php

namespace App\Filament\Admin\Resources\Funnels\Pages;

use App\Filament\Admin\Resources\Funnels\Concerns\LogsFunnelAdminActivity;
use App\Filament\Admin\Resources\Funnels\FunnelResource;
use App\Modules\Order\Enums\FunnelStatus;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditFunnel extends EditRecord
{
    use LogsFunnelAdminActivity;

    protected static string $resource = FunnelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->after(fn () => $this->logFunnelActivity('deleted', "Funnel {$this->record->name} soft deleted")),
            ForceDeleteAction::make()
                ->after(fn () => $this->logFunnelActivity('force_deleted', "Funnel {$this->record->name} permanently deleted")),
            RestoreAction::make()
                ->after(fn () => $this->logFunnelActivity('restored', "Funnel {$this->record->name} restored")),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $existingSettings = $this->record->settings ?? [];
        $incomingSettings = (array) ($data['settings'] ?? []);
        $data['settings'] = array_merge($existingSettings, array_filter($incomingSettings, fn (mixed $value): bool => $value !== null && $value !== ''));

        if (($data['status'] ?? '') === FunnelStatus::Active->value && blank($data['published_at'] ?? null)) {
            $data['published_at'] = now();
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $this->logFunnelActivity('updated', "Funnel {$this->record->name} updated");
    }
}
