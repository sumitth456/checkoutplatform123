<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Resources\DelayFulfillment\DelayFulfillmentResource;
use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Support\Admin\AdminAuthorization;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class CustomersFulfillmentPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::CUSTOMERS;

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Fulfillment';

    protected static ?string $slug = 'customers/fulfillment';

    protected string $view = 'filament.admin.pages.customers-fulfillment-redirect';

    public static function getNavigationUrl(): string
    {
        return DelayFulfillmentResource::getUrl('index');
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return AdminAuthorization::can($user, 'fulfillment.view')
            || AdminAuthorization::can($user, 'delay_fulfillment.manage')
            || AdminAuthorization::can($user, 'delay_fulfillment.export');
    }

    public function mount(): void
    {
        $this->redirect(DelayFulfillmentResource::getUrl('index'), navigate: true);
    }
}
