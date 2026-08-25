<?php

namespace App\Filament\Admin\Resources\Subscriptions\Pages;

use App\Filament\Admin\Resources\Subscriptions\Concerns\InteractsWithSubscriptionBillingActions;
use App\Filament\Admin\Resources\Subscriptions\Concerns\LogsSubscriptionAdminActivity;
use App\Filament\Admin\Resources\Subscriptions\SubscriptionResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditSubscription extends EditRecord
{
    use InteractsWithSubscriptionBillingActions;
    use LogsSubscriptionAdminActivity;

    protected static string $resource = SubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...$this->getSubscriptionBillingActions(),
            DeleteAction::make()
                ->after(fn () => $this->logSubscriptionActivity('deleted', 'Subscription soft deleted')),
            ForceDeleteAction::make()
                ->after(fn () => $this->logSubscriptionActivity('force_deleted', 'Subscription permanently deleted')),
            RestoreAction::make()
                ->after(fn () => $this->logSubscriptionActivity('restored', 'Subscription restored')),
        ];
    }

    protected function afterSave(): void
    {
        $this->logSubscriptionActivity('updated', 'Subscription record updated');
    }
}
