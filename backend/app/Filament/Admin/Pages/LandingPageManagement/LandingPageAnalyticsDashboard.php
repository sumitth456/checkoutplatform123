<?php

namespace App\Filament\Admin\Pages\LandingPageManagement;

use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Filament\Admin\Support\AdminNavigationParent;
use App\Filament\Admin\Widgets\LandingPages\LandingPageAnalyticsKpiWidget;
use App\Filament\Admin\Widgets\LandingPages\LandingPageTopPagesWidget;
use App\Filament\Admin\Widgets\LandingPages\LandingPageViewsTrendWidget;
use App\Modules\Analytics\Data\AnalyticsPeriod;
use App\Support\Admin\AdminAuthorization;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;
use UnitEnum;

class LandingPageAnalyticsDashboard extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::ADMIN;

    protected static ?string $navigationParentItem = AdminNavigationParent::LANDING_PAGES;

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Analytics';

    protected static ?string $title = 'Landing page analytics';

    protected static ?string $slug = 'landing-pages/analytics';

    #[Url(as: 'from')]
    public ?string $dateFrom = null;

    #[Url(as: 'to')]
    public ?string $dateTo = null;

    public static function canAccess(): bool
    {
        return AdminAuthorization::can(auth()->user(), 'landing_pages.view');
    }

    public function mount(): void
    {
        $this->dateFrom ??= now()->subDays(29)->toDateString();
        $this->dateTo ??= now()->toDateString();
    }

    public function getPeriod(): AnalyticsPeriod
    {
        return AnalyticsPeriod::fromRequest($this->dateFrom, $this->dateTo);
    }

    /**
     * @return array<string, mixed>
     */
    public function getWidgetData(): array
    {
        return [
            'dateFrom' => $this->dateFrom,
            'dateTo' => $this->dateTo,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('last7')
                ->label('Last 7 days')
                ->action(fn () => $this->setRange(now()->subDays(6)->toDateString(), now()->toDateString())),
            Action::make('last30')
                ->label('Last 30 days')
                ->action(fn () => $this->setRange(now()->subDays(29)->toDateString(), now()->toDateString())),
        ];
    }

    /**
     * @return array<class-string>
     */
    public function getFooterWidgets(): array
    {
        return [
            LandingPageAnalyticsKpiWidget::class,
            LandingPageViewsTrendWidget::class,
            LandingPageTopPagesWidget::class,
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            SchemaView::make('filament.admin.pages.landing-page-analytics-filters'),
            Grid::make(2)
                ->schema(fn (): array => $this->getWidgetsSchemaComponents($this->getFooterWidgets())),
        ]);
    }

    private function setRange(string $from, string $to): void
    {
        $this->dateFrom = $from;
        $this->dateTo = $to;
    }
}
