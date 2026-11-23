<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Filament\Admin\Support\Concerns\InteractsWithDashboardFilters;
use App\Filament\Admin\Widgets\RebillRecovery\RebillRecoveryOutcomeChartWidget;
use App\Filament\Admin\Widgets\RebillRecovery\RebillRecoveryTrendChartWidget;
use App\Modules\Admin\Services\GlobalDashboardFilterService;
use App\Modules\Analytics\Services\RebillRecoveryAnalyticsService;
use App\Modules\Analytics\Services\RebillRecoveryExcelExportService;
use App\Support\Admin\AdminActivityLogger;
use App\Support\Admin\AdminAuthorization;
use App\Support\Admin\CrmDateTime;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

class RebillRecoveryAnalytics extends Page
{
    use InteractsWithDashboardFilters;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::ANALYTICS;

    protected static ?int $navigationSort = 25;

    protected static ?string $navigationLabel = 'Rebill Recovery';

    protected static ?string $title = 'Rebill Recovery & Retry Analytics';

    protected static ?string $slug = 'analytics/rebill-recovery';

    protected string $view = 'filament.admin.pages.rebill-recovery-analytics';

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

    #[Url(as: 'rr_kpi')]
    public ?string $kpiFilter = null;

    #[Url(as: 'rr_report')]
    public ?string $reportFilter = null;

    #[Url(as: 'rr_page')]
    public int $reportPage = 1;

    #[Url(as: 'rr_per_page')]
    public int $reportPerPage = 25;

    public static function canAccess(): bool
    {
        return AdminAuthorization::can(auth()->user(), 'analytics.view');
    }

    public function mount(): void
    {
        $this->currency ??= 'USD';

        if ($this->quickRange !== 'custom' && ! $this->dateFrom && ! $this->dateTo) {
            [$from, $to] = app(GlobalDashboardFilterService::class)->resolveQuickRange($this->quickRange);
            $crmTz = app(CrmDateTime::class)->iana();
            $this->dateFrom = $from->copy()->timezone($crmTz)->format('Y-m-d\TH:i');
            $this->dateTo = $to->copy()->timezone($crmTz)->format('Y-m-d\TH:i');
        }

        AdminActivityLogger::log(
            module: 'analytics',
            event: 'rebill_recovery_viewed',
            description: 'Rebill recovery analytics viewed',
        );
    }

    public function getTitle(): string|Htmlable
    {
        return 'Rebill Recovery & Retry Analytics';
    }

    public function updatedQuickRange(string $value): void
    {
        if ($value === 'custom') {
            return;
        }

        [$from, $to] = app(GlobalDashboardFilterService::class)->resolveQuickRange($value);
        $crmTz = app(CrmDateTime::class)->iana();
        $this->dateFrom = $from->copy()->timezone($crmTz)->format('Y-m-d\TH:i');
        $this->dateTo = $to->copy()->timezone($crmTz)->format('Y-m-d\TH:i');
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
        return $this->getWidgetDataFromProps();
    }

    /**
     * @return array<string, mixed>
     */
    public function getWidgetDataFromProps(): array
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
     * @return array<string, mixed>
     */
    public function safeSnapshot(): array
    {
        try {
            return $this->snapshot();
        } catch (\Throwable $exception) {
            report($exception);

            return [
                'error' => true,
                'period' => [],
                'limitations' => app(RebillRecoveryAnalyticsService::class)->limitations(),
                'kpis' => [],
                'recovery_by_attempt' => [],
                'recovery_sequence' => [],
                'payment_attempt_statistics' => [],
                'featured_journeys' => [],
                'cycle_analytics' => [],
                'attempt_analytics' => [],
                'subscription_histories' => [],
                'showcase' => null,
                'rates' => [],
                'charts' => [],
                'rows' => [],
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $filter = $this->dashboardFilter();

        return app(RebillRecoveryAnalyticsService::class)->snapshot(
            $filter,
            $this->kpiFilter,
            $this->reportFilter ?? $this->kpiFilter,
        );
    }

    public function setKpiFilter(?string $key): void
    {
        $this->kpiFilter = $key;
        $this->reportFilter = $key;
        $this->reportPage = 1;
    }

    public function clearReportFilter(): void
    {
        $this->kpiFilter = null;
        $this->reportFilter = null;
        $this->reportPage = 1;
    }

    public function goToReportPage(int $page): void
    {
        $this->reportPage = max(1, $page);
    }

    public function previousReportPage(): void
    {
        $this->goToReportPage($this->reportPage - 1);
    }

    public function nextReportPage(): void
    {
        $this->goToReportPage($this->reportPage + 1);
    }

    public function updatedReportPerPage(): void
    {
        $this->reportPerPage = $this->normalizeReportPerPage($this->reportPerPage);
        $this->reportPage = 1;
    }

    public function applyFilters(): void
    {
        if ($this->quickRange !== 'custom') {
            $this->updatedQuickRange($this->quickRange);
        }

        $this->reportPage = 1;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{rows: array<int, array<string, mixed>>, total: int, page: int, per_page: int, last_page: int, from: int, to: int}
     */
    public function paginateReportRows(array $rows): array
    {
        $perPage = $this->normalizeReportPerPage($this->reportPerPage);
        $total = count($rows);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $this->reportPage), $lastPage);

        if ($page !== $this->reportPage) {
            $this->reportPage = $page;
        }

        $offset = $total > 0 ? ($page - 1) * $perPage : 0;

        return [
            'rows' => array_slice($rows, $offset, $perPage),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => $lastPage,
            'from' => $total > 0 ? $offset + 1 : 0,
            'to' => min($offset + $perPage, $total),
        ];
    }

    /**
     * @return array<int, int>
     */
    public function reportPerPageOptions(): array
    {
        return [25, 50, 100, 250];
    }

    public function reportFilterLabel(?string $filter): ?string
    {
        return match ($filter) {
            'due', 'attempted' => 'All rebill cycles',
            'success_first' => 'Successful on first attempt',
            'failed_first' => 'Initially failed',
            'recovered' => 'Recovered on retry',
            'unrecovered' => 'Permanently unrecovered',
            'in_progress' => 'Recovery in progress',
            default => null,
        };
    }

    private function normalizeReportPerPage(int $perPage): int
    {
        return in_array($perPage, $this->reportPerPageOptions(), true) ? $perPage : 25;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_excel')
                ->label('Download Complete Excel Report')
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->color('primary')
                ->modalHeading('Download rebill recovery workbook')
                ->modalDescription('Nine worksheets: executive summary, attempts, cycles, campaign/gateway performance, failures, in-progress, and unrecovered. Summary KPIs always match the dashboard; choose whether detail sheets include all cycles or only the current status filter.')
                ->form([
                    Select::make('detail_scope')
                        ->label('Detail sheets (cycles & attempts)')
                        ->options([
                            'all' => 'All records in reporting period',
                            'filtered' => 'Current dashboard status filter only',
                        ])
                        ->default(fn (): string => filled($this->reportFilter ?? $this->kpiFilter) ? 'filtered' : 'all')
                        ->required()
                        ->native(false),
                ])
                ->action(fn (array $data) => $this->exportExcel($data['detail_scope'] ?? 'all')),
            Action::make('export_csv')
                ->label('Export report CSV')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->action('exportCsv'),
        ];
    }

    public function exportExcel(string $detailScope = 'all')
    {
        if (! static::canAccess()) {
            abort(403);
        }

        $filter = $this->dashboardFilter();
        $reportFilter = $detailScope === 'filtered'
            ? ($this->reportFilter ?? $this->kpiFilter)
            : null;

        AdminActivityLogger::log(
            module: 'analytics',
            event: 'rebill_recovery_excel_exported',
            description: 'Rebill recovery Excel workbook downloaded',
            properties: [
                'detail_scope' => $detailScope,
                'report_filter' => $reportFilter,
                'period_from' => $filter->from->toIso8601String(),
                'period_to' => $filter->to->toIso8601String(),
            ],
        );

        return app(RebillRecoveryExcelExportService::class)->downloadResponse($filter, $reportFilter);
    }

    public function exportCsv(): StreamedResponse
    {
        $filter = $this->dashboardFilter();
        $data = app(RebillRecoveryAnalyticsService::class)->snapshot(
            $filter,
            $this->kpiFilter,
            $this->reportFilter ?? $this->kpiFilter,
        );
        $rows = $data['rows'] ?? [];
        $from = $data['period']['from'] ?? '';
        $to = $data['period']['to'] ?? '';
        $filename = 'rebill-recovery-'.Carbon::now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($rows, $from, $to): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Reporting period from',
                $from,
                'to',
                $to,
            ]);
            fputcsv($handle, []);
            fputcsv($handle, [
                'Customer',
                'Customer ID',
                'Subscription ID',
                'Order',
                'Campaign',
                'Cycle step',
                'Initial attempt',
                'Initial result',
                'Retries',
                'Final status',
                'Recovery attempt #',
                'Original amount',
                'Total collected',
                'Recovered revenue',
            ]);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['customer_name'],
                    $row['customer_id'],
                    $row['subscription_id'],
                    $row['order_id'],
                    $row['campaign'],
                    $row['cycle_step'],
                    $row['initial_attempt_at'],
                    $row['initial_result'],
                    $row['retry_count'],
                    $row['final_status'],
                    $row['recovery_attempt_number'],
                    $row['original_amount'],
                    $row['total_collected'],
                    $row['recovered_revenue'],
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array<class-string>
     */
    public function getChartWidgets(): array
    {
        return [
            RebillRecoveryOutcomeChartWidget::class,
            RebillRecoveryTrendChartWidget::class,
        ];
    }

    public function kpiUrl(?string $filter): string
    {
        $params = array_merge($this->dashboardFilter()->toQueryParams(), [
            'rr_kpi' => $filter,
            'rr_report' => $filter,
            'rr_page' => 1,
        ]);

        return static::getUrl($params);
    }

    public function clearReportFilterUrl(): string
    {
        $params = array_merge($this->dashboardFilter()->toQueryParams(), [
            'rr_page' => 1,
            'rr_per_page' => $this->normalizeReportPerPage($this->reportPerPage),
        ]);

        return static::getUrl($params);
    }

    public function getPageClasses(): array
    {
        return [
            'fi-rebill-recovery-analytics-page',
            'cp-dashboard-page',
        ];
    }
}
