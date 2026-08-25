<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Filament\Admin\Widgets\AchAnalyticsWidget;
use App\Filament\Admin\Widgets\Analytics\AnalyticsGatewayHealthWidget;
use App\Filament\Admin\Widgets\Analytics\AnalyticsKpiOverviewWidget;
use App\Filament\Admin\Widgets\Analytics\AnalyticsOrdersTrendWidget;
use App\Filament\Admin\Widgets\Analytics\AnalyticsRevenueTrendWidget;
use App\Filament\Admin\Widgets\Analytics\AnalyticsTopFunnelsWidget;
use App\Filament\Admin\Widgets\Analytics\AnalyticsTopProductsWidget;
use App\Modules\Admin\Services\GlobalDashboardFilterService;
use App\Support\Admin\AdminActivityLogger;
use App\Support\Admin\AdminAuthorization;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Schema;
use Filament\Widgets\WidgetConfiguration;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Url;
use UnitEnum;

class AnalyticsInsights extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::ADMIN;

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'Analytics';

    protected static ?string $title = 'Analytics';

    protected static ?string $slug = 'analytics';

    #[Url(as: 'df_range')]
    public string $quickRange = 'last_30_days';

    #[Url(as: 'df_from')]
    public ?string $dateFrom = null;

    #[Url(as: 'df_to')]
    public ?string $dateTo = null;

    #[Url(as: 'df_currency')]
    public ?string $currency = 'USD';

    #[Url(as: 'df_campaign')]
    public ?int $campaignId = null;

    #[Url(as: 'df_category')]
    public ?string $campaignCategory = null;

    #[Url(as: 'df_traffic')]
    public ?string $trafficSource = null;

    #[Url(as: 'df_affiliate')]
    public ?int $affiliateId = null;

    #[Url(as: 'df_gateway')]
    public ?int $paymentGatewayId = null;

    #[Url(as: 'df_fhouse')]
    public ?int $fulfillmentHouseId = null;

    public static function canAccess(): bool
    {
        return AdminAuthorization::can(auth()->user(), 'analytics.view');
    }

    public function mount(): void
    {
        if (! $this->dateFrom && request()->has('from')) {
            $this->dateFrom = request()->query('from');
        }

        if (! $this->dateTo && request()->has('to')) {
            $this->dateTo = request()->query('to');
        }

        $this->currency ??= 'USD';

        if ($this->quickRange !== 'custom' && ! $this->dateFrom && ! $this->dateTo) {
            [$from, $to] = app(GlobalDashboardFilterService::class)->resolveQuickRange($this->quickRange);
            $this->dateFrom = $from->format('Y-m-d\TH:i');
            $this->dateTo = $to->format('Y-m-d\TH:i');
        }

        AdminActivityLogger::log(
            module: 'analytics',
            event: 'viewed',
            description: 'Analytics dashboard viewed',
        );
    }

    public function getTitle(): string|Htmlable
    {
        return 'Analytics';
    }

    public function updatedQuickRange(string $value): void
    {
        if ($value === 'custom') {
            return;
        }

        [$from, $to] = app(GlobalDashboardFilterService::class)->resolveQuickRange($value);
        $this->dateFrom = $from->format('Y-m-d\TH:i');
        $this->dateTo = $to->format('Y-m-d\TH:i');
    }

    public function applyFilters(): void
    {
        if ($this->quickRange !== 'custom') {
            $this->updatedQuickRange($this->quickRange);
        }
    }

    /**
     * @param  array<string | WidgetConfiguration>  $widgets
     * @param  array<string, mixed>  $data
     * @return array<Component>
     */
    public function getWidgetsSchemaComponents(array $widgets, array $data = []): array
    {
        $filterKey = md5(json_encode($this->getWidgetData()));

        return collect($widgets)
            ->values()
            ->filter(fn (string | WidgetConfiguration $widget): bool => $this->normalizeWidgetClass($widget)::canView())
            ->map(fn (string | WidgetConfiguration $widget, int $widgetKey): Livewire => Livewire::make(
                $widgetClass = $this->normalizeWidgetClass($widget),
                fn (): array => [
                    ...$this->getWidgetData(),
                    ...$data,
                    ...(($widget instanceof WidgetConfiguration) ? [
                        ...$widget->widget::getDefaultProperties(),
                        ...$widget->getProperties(),
                    ] : $widget::getDefaultProperties()),
                ],
            )->key("{$widgetClass}-{$widgetKey}-{$filterKey}"))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function filterOptions(): array
    {
        $service = app(GlobalDashboardFilterService::class);

        return [
            'quickRanges' => $service->quickRangeOptions(),
            'campaigns' => $service->campaignOptions(),
            'categories' => $service->campaignCategoryOptions(),
            'affiliates' => $service->affiliateOptions(),
            'gateways' => $service->gatewayOptions(),
            'fulfillmentHouses' => $service->fulfillmentHouseOptions(),
            'trafficSources' => $service->trafficSourceOptions(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getWidgetData(): array
    {
        return [
            'quickRange' => $this->quickRange,
            'dateFrom' => $this->dateFrom,
            'dateTo' => $this->dateTo,
            'currency' => $this->currency,
            'campaignId' => $this->campaignId,
            'campaignCategory' => $this->campaignCategory,
            'trafficSource' => $this->trafficSource,
            'affiliateId' => $this->affiliateId,
            'paymentGatewayId' => $this->paymentGatewayId,
            'fulfillmentHouseId' => $this->fulfillmentHouseId,
        ];
    }

    /**
     * @return array<class-string>
     */
    public function getFooterWidgets(): array
    {
        return [];
    }

    /**
     * @param  array<class-string>  $widgets
     * @return array<Component>
     */
    protected function analyticsWidgetGroup(array $widgets): array
    {
        return $this->getWidgetsSchemaComponents($widgets);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                SchemaView::make('filament.admin.pages.analytics-filters')
                    ->viewData(fn (): array => [
                        'options' => $this->filterOptions(),
                    ]),
                SchemaView::make('filament.admin.pages.analytics-section-header')
                    ->viewData(fn (): array => [
                        'title' => 'Overview',
                        'subtitle' => 'Key metrics for your selected period',
                    ]),
                Grid::make(1)
                    ->schema(fn (): array => $this->analyticsWidgetGroup([
                        AnalyticsKpiOverviewWidget::class,
                    ])),
                SchemaView::make('filament.admin.pages.analytics-section-header')
                    ->viewData(fn (): array => [
                        'title' => 'Revenue',
                        'subtitle' => 'Daily net revenue trend',
                    ]),
                Grid::make(1)
                    ->schema(fn (): array => $this->analyticsWidgetGroup([
                        AnalyticsRevenueTrendWidget::class,
                    ])),
                SchemaView::make('filament.admin.pages.analytics-section-header')
                    ->viewData(fn (): array => [
                        'title' => 'Orders',
                        'subtitle' => 'Order volume over time',
                    ]),
                Grid::make(1)
                    ->schema(fn (): array => $this->analyticsWidgetGroup([
                        AnalyticsOrdersTrendWidget::class,
                    ])),
                SchemaView::make('filament.admin.pages.analytics-section-header')
                    ->viewData(fn (): array => [
                        'title' => 'Payments',
                        'subtitle' => 'Gateway performance and success rates',
                    ]),
                Grid::make(1)
                    ->schema(fn (): array => $this->analyticsWidgetGroup([
                        AnalyticsGatewayHealthWidget::class,
                    ])),
                SchemaView::make('filament.admin.pages.analytics-section-header')
                    ->viewData(fn (): array => [
                        'title' => 'ACH payments',
                        'subtitle' => 'Bank transfer revenue, success rate, and rebills',
                    ]),
                Grid::make(1)
                    ->schema(fn (): array => $this->analyticsWidgetGroup([
                        AchAnalyticsWidget::class,
                    ])),
                SchemaView::make('filament.admin.pages.analytics-section-header')
                    ->viewData(fn (): array => [
                        'title' => 'Top performers',
                        'subtitle' => 'Best products and funnels by revenue',
                    ]),
                Grid::make([
                    'default' => 1,
                    'lg' => 2,
                ])
                    ->schema(fn (): array => $this->analyticsWidgetGroup([
                        AnalyticsTopProductsWidget::class,
                        AnalyticsTopFunnelsWidget::class,
                    ])),
            ]);
    }

    public function getPageClasses(): array
    {
        return [
            'fi-analytics-insights-page',
            'cp-dashboard-page',
        ];
    }
}
