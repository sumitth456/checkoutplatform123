<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Resources\Customers\CustomerResource;
use App\Filament\Admin\Support\Concerns\InteractsWithDashboardFilters;
use App\Models\Customer;
use App\Modules\Admin\Services\DashboardMetricsService;
use App\Modules\Admin\Services\GlobalDashboardFilterService;
use App\Modules\Admin\Services\OperationalDashboardService;
use App\Modules\Analytics\Data\AnalyticsPeriod;
use App\Support\Admin\AdminActivityLogger;
use App\Support\Admin\AdminAuthorization;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Url;

class AdminDashboard extends BaseDashboard
{
    use InteractsWithDashboardFilters;

    protected string $view = 'filament.admin.pages.operations-dashboard-page';

    protected static ?string $title = 'Operations Dashboard';

    protected static ?string $navigationLabel = 'Dashboard';

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
        return AdminAuthorization::can(auth()->user(), 'dashboard.view');
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
            module: 'dashboard',
            event: 'viewed',
            description: 'Operations dashboard viewed',
        );
    }

    public function getTitle(): string|Htmlable
    {
        return 'Operations Dashboard';
    }

    /**
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [];
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
     * @return array<string, string>
     */
    public function dashboardQuickRangeOptions(): array
    {
        $all = app(GlobalDashboardFilterService::class)->quickRangeOptions();

        return array_intersect_key($all, array_flip([
            'today',
            'yesterday',
            'last_7_days',
            'last_30_days',
            'this_month',
            'last_month',
            'custom',
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    public function filterOptions(): array
    {
        $service = app(GlobalDashboardFilterService::class);

        return [
            'quickRanges' => $this->dashboardQuickRangeOptions(),
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
    public function activeFilterSummary(): array
    {
        $labels = $this->dashboardQuickRangeOptions();
        $rangeLabel = $labels[$this->quickRange] ?? ucfirst(str_replace('_', ' ', $this->quickRange));

        return [
            'range' => $rangeLabel,
            'from' => $this->dateFrom,
            'to' => $this->dateTo,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function safeMetrics(): array
    {
        try {
            $filter = $this->dashboardFilter();
            $metrics = app(OperationalDashboardService::class)->snapshot($filter);

            $period = new AnalyticsPeriod($filter->from, $filter->to, $filter->currency ?? 'USD');
            $metrics['period'] = app(DashboardMetricsService::class)->periodSnapshot($period);

            $customerParams = http_build_query($filter->toQueryParams());
            $metrics['customers'] = [
                'value' => (int) Customer::query()
                    ->whereBetween('created_at', [$filter->from, $filter->to])
                    ->count(),
                'url' => CustomerResource::getUrl('index').($customerParams ? '?'.$customerParams : ''),
            ];

            return $metrics;
        } catch (\Throwable $exception) {
            report($exception);

            return [
                'kpis' => [],
                'sales' => [],
                'customer_service' => [],
                'fulfillment' => [],
                'subscriptions' => [],
                'affiliates' => [],
                'gateways' => [],
                'charts' => ['revenue' => [], 'orders' => [], 'subscriptions' => [], 'rebills' => []],
                'tops' => [],
                'period' => [],
                'customers' => ['value' => 0, 'url' => CustomerResource::getUrl('index')],
            ];
        }
    }

    public function getPageClasses(): array
    {
        return [
            'fi-admin-dashboard-page',
            'cp-dashboard-page',
        ];
    }
}
