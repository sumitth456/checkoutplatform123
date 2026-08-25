<?php

namespace App\Filament\Admin\Resources\Funnels\Pages;

use App\Filament\Admin\Resources\Funnels\Concerns\LogsFunnelAdminActivity;
use App\Filament\Admin\Pages\ManageFunnelProducts;
use App\Filament\Admin\Resources\Funnels\FunnelResource;
use App\Support\Admin\AdminAuthorization;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\ViewRecord;

class ViewFunnel extends ViewRecord
{
    use LogsFunnelAdminActivity;

    protected static string $resource = FunnelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('manage_products')
                ->label('Campaign products')
                ->icon('heroicon-o-rectangle-stack')
                ->url(fn (): string => ManageFunnelProducts::getUrl(['record' => $this->record]))
                ->visible(fn (): bool => AdminAuthorization::can(auth()->user(), 'funnels.products.manage')),
            EditAction::make(),
            DeleteAction::make()
                ->after(fn () => $this->logFunnelActivity('deleted', "Funnel {$this->record->name} soft deleted")),
            ForceDeleteAction::make()
                ->after(fn () => $this->logFunnelActivity('force_deleted', "Funnel {$this->record->name} permanently deleted")),
            RestoreAction::make()
                ->after(fn () => $this->logFunnelActivity('restored', "Funnel {$this->record->name} restored")),
        ];
    }
}
