<?php

namespace App\Filament\Admin\Pages\CampaignManagement;

use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Filament\Admin\Support\AdminNavigationParent;
use App\Support\Admin\AdminAuthorization;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class CampaignTemplatesPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::CRM;

    protected static ?string $navigationParentItem = AdminNavigationParent::CAMPAIGNS;

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Campaign Templates';

    protected static ?string $slug = 'campaign-management/templates';

    protected static ?string $title = 'Campaign Templates';

    public static function canAccess(): bool
    {
        return AdminAuthorization::can(auth()->user(), 'campaigns.view');
    }

    protected string $view = 'filament.admin.pages.campaign-module-placeholder';

    public function getViewData(): array
    {
        return [
            'heading' => 'Campaign Templates',
            'message' => 'Reusable campaign blueprints will be configured in Module 2 (Campaign Templates).',
        ];
    }
}
