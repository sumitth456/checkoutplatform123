<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Resources\Affiliates\AffiliateResource;
use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Filament\Admin\Support\AdminNavigationParent;
use App\Filament\Admin\Widgets\Affiliate\AffiliateClicksOrdersChartWidget;
use App\Filament\Admin\Widgets\Affiliate\AffiliateStatsWidget;
use App\Filament\Admin\Widgets\Affiliate\TopAffiliatesWidget;
use App\Filament\Admin\Widgets\Affiliate\TopOffersWidget;
use App\Support\Admin\AdminActivityLogger;
use App\Support\Admin\AdminAuthorization;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

class AffiliateDashboard extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::CRM;

    protected static ?string $navigationParentItem = AdminNavigationParent::AFFILIATES;

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?string $title = 'Affiliate Dashboard';

    protected static ?string $slug = 'affiliate-dashboard';

    public static function canAccess(): bool
    {
        return AdminAuthorization::can(auth()->user(), 'affiliate_dashboard.view');
    }

    public function getTitle(): string|Htmlable
    {
        return 'Affiliate Dashboard';
    }

    public function mount(): void
    {
        AdminActivityLogger::log(
            module: 'affiliates',
            event: 'viewed',
            description: 'Affiliate dashboard viewed',
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('New affiliate')
                ->url(AffiliateResource::getUrl('create')),
        ];
    }

    /**
     * @return array<class-string>
     */
    public function getFooterWidgets(): array
    {
        return [
            AffiliateStatsWidget::class,
            AffiliateClicksOrdersChartWidget::class,
            TopAffiliatesWidget::class,
            TopOffersWidget::class,
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)
                ->schema(fn (): array => $this->getWidgetsSchemaComponents($this->getFooterWidgets())),
        ]);
    }
}
