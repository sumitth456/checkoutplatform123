<?php

namespace App\Filament\Admin\Pages\CampaignManagement;

use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Filament\Admin\Support\AdminNavigationParent;
use App\Support\Admin\AdminAuthorization;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class CampaignAbTestsPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::CRM;

    protected static ?string $navigationParentItem = AdminNavigationParent::CAMPAIGNS;

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'A/B Tests';

    protected static ?string $slug = 'campaign-management/ab-tests';

    protected static ?string $title = 'Campaign A/B Tests';

    public static function canAccess(): bool
    {
        return AdminAuthorization::can(auth()->user(), 'campaigns.view');
    }

    protected string $view = 'filament.admin.pages.campaign-module-placeholder';

    public function getViewData(): array
    {
        return [
            'heading' => 'A/B Tests',
            'message' => 'Traffic split experiments and winner selection will ship in Module 4 (A/B Testing).',
        ];
    }
}
