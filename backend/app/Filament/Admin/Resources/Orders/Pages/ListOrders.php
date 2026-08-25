<?php

namespace App\Filament\Admin\Resources\Orders\Pages;

use App\Filament\Admin\Exports\OrderExporter;
use App\Filament\Admin\Resources\Orders\OrderResource;
use App\Filament\Admin\Support\Concerns\InteractsWithDashboardFilters;
use App\Models\Order;
use App\Modules\Admin\Services\DashboardFilterApplicator;
use App\Modules\Admin\Services\GlobalDashboardFilterService;
use App\Modules\Admin\Services\OrderSearchFilterApplicator;
use App\Modules\Order\Enums\OrderPaymentStatus;
use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Order\Enums\OrderType;
use App\Support\Admin\AdminAuthorization;
use Carbon\Carbon;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Schema;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Livewire\Attributes\Url;

class ListOrders extends ListRecords
{
    use InteractsWithDashboardFilters;

    protected static string $resource = OrderResource::class;

    #[Url(as: 'df_range', except: 'all_time')]
    public string $quickRange = 'all_time';

    #[Url(as: 'quick_range')]
    public ?string $quickRangeAlias = null;

    #[Url(as: 'df_from')]
    public ?string $dateFrom = null;

    #[Url(as: 'df_to')]
    public ?string $dateTo = null;

    #[Url(as: 'of_order_id')]
    public ?string $searchOrderId = null;

    #[Url(as: 'of_first_name')]
    public ?string $searchFirstName = null;

    #[Url(as: 'of_last_name')]
    public ?string $searchLastName = null;

    #[Url(as: 'of_phone')]
    public ?string $searchPhone = null;

    #[Url(as: 'of_phone_exact')]
    public bool $searchPhoneExact = false;

    #[Url(as: 'of_email')]
    public ?string $searchEmail = null;

    #[Url(as: 'of_email_exact')]
    public bool $searchEmailExact = false;

    #[Url(as: 'of_company')]
    public ?string $searchCompany = null;

    #[Url(as: 'of_status')]
    public ?string $searchStatus = null;

    #[Url(as: 'of_order_type')]
    public ?string $searchOrderType = null;

    #[Url(as: 'of_campaign')]
    public ?string $searchCampaignId = null;

    #[Url(as: 'of_customer_id')]
    public ?string $searchCustomerId = null;

    #[Url(as: 'of_ip')]
    public ?string $searchIpAddress = null;

    #[Url(as: 'of_payment_status')]
    public ?string $searchPaymentStatus = null;

    #[Url(as: 'of_state')]
    public ?string $searchState = null;

    #[Url(as: 'of_country')]
    public ?string $searchCountry = null;

    #[Url(as: 'of_currency')]
    public ?string $searchCurrency = null;

    #[Url(as: 'of_more')]
    public bool $showMoreSearchFilters = false;

    public function mount(): void
    {
        parent::mount();

        if (filled($this->quickRangeAlias)) {
            $this->quickRange = (string) $this->quickRangeAlias;
        }

        if (! $this->dateFrom && request()->has('from')) {
            $this->dateFrom = request()->query('from');
        }

        if (! $this->dateTo && request()->has('to')) {
            $this->dateTo = request()->query('to');
        }

        if ($this->quickRange !== 'custom' && $this->quickRange !== 'all_time' && ! $this->dateFrom && ! $this->dateTo) {
            [$from, $to] = app(GlobalDashboardFilterService::class)->resolveQuickRange($this->quickRange);
            $this->dateFrom = $from->format('Y-m-d\TH:i');
            $this->dateTo = $to->format('Y-m-d\TH:i');
        }
    }

    public function updatedQuickRange(string $value): void
    {
        if ($value === 'custom' || $value === 'all_time') {
            if ($value === 'all_time') {
                $this->dateFrom = null;
                $this->dateTo = null;
            }

            return;
        }

        [$from, $to] = app(GlobalDashboardFilterService::class)->resolveQuickRange($value);
        $this->dateFrom = $from->format('Y-m-d\TH:i');
        $this->dateTo = $to->format('Y-m-d\TH:i');
    }

    public function applyFilters(): void
    {
        if ($this->quickRange !== 'custom' && $this->quickRange !== 'all_time') {
            $this->updatedQuickRange($this->quickRange);
        }

        $this->quickRangeAlias = $this->quickRange === 'all_time' ? null : $this->quickRange;
        $this->resetTable();
    }

    public function applySearchFilters(): void
    {
        $this->resetTable();
    }

    public function toggleMoreFilters(): void
    {
        $this->showMoreSearchFilters = ! $this->showMoreSearchFilters;
    }

    public function resetFilters(): void
    {
        $this->quickRange = 'all_time';
        $this->quickRangeAlias = null;
        $this->dateFrom = null;
        $this->dateTo = null;
        $this->resetTable();
    }

    public function resetSearchFilters(): void
    {
        $this->searchOrderId = null;
        $this->searchFirstName = null;
        $this->searchLastName = null;
        $this->searchPhone = null;
        $this->searchPhoneExact = false;
        $this->searchEmail = null;
        $this->searchEmailExact = false;
        $this->searchCompany = null;
        $this->searchStatus = null;
        $this->searchOrderType = null;
        $this->searchCampaignId = null;
        $this->searchCustomerId = null;
        $this->searchIpAddress = null;
        $this->searchPaymentStatus = null;
        $this->searchState = null;
        $this->searchCountry = null;
        $this->searchCurrency = null;
        $this->resetTable();
    }

    public function updatedActiveTab(): void
    {
        parent::updatedActiveTab();
        $this->resetTable();
    }

    protected function getTableQuery(): ?Builder
    {
        $query = parent::getTableQuery();

        if (! $query) {
            return null;
        }

        $request = $this->ordersFilterRequest();

        $query = app(DashboardFilterApplicator::class)->applyToOrders($query, $request);

        return app(OrderSearchFilterApplicator::class)->apply($query, $request);
    }

    protected function getTableHeader(): ?View
    {
        return view('filament.admin.resources.orders.search-filters', [
            'options' => $this->searchFilterOptions(),
            'showMore' => $this->showMoreSearchFilters,
            'hasActiveSearch' => $this->hasActiveSearchFilters(),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make()
                ->exporter(OrderExporter::class)
                ->authorize(fn (): bool => AdminAuthorization::can(auth()->user(), 'orders.export')),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All orders')
                ->badge((string) $this->orderCount()),
            'initial' => Tab::make('Initial orders')
                ->badge((string) $this->orderCount(OrderType::Initial))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('order_type', OrderType::Initial)),
            'rebill' => Tab::make('Rebill orders')
                ->badge((string) $this->orderCount(OrderType::Rebill))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('order_type', OrderType::Rebill)),
        ];
    }

    public function getPageClasses(): array
    {
        return array_merge(parent::getPageClasses(), [
            'cp-orders-list-page',
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                SchemaView::make('filament.admin.resources.orders.date-filters')
                    ->viewData(fn (): array => [
                        'options' => app(GlobalDashboardFilterService::class)->orderDateRangeOptions(),
                        'summary' => $this->activeFilterSummary(),
                    ]),
                $this->getTabsContentComponent(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
                EmbeddedTable::make(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function searchFilterOptions(): array
    {
        return [
            'statuses' => collect(OrderStatus::cases())->mapWithKeys(
                fn (OrderStatus $status): array => [$status->value => str($status->name)->headline()]
            )->all(),
            'orderTypes' => [
                OrderType::Initial->value => 'Initial',
                OrderType::Rebill->value => 'Rebill',
            ],
            'paymentStatuses' => collect(OrderPaymentStatus::cases())->mapWithKeys(
                fn (OrderPaymentStatus $status): array => [$status->value => str($status->name)->headline()]
            )->all(),
            'campaigns' => app(GlobalDashboardFilterService::class)
                ->campaignOptions()
                ->pluck('name', 'id')
                ->all(),
            'states' => app(GlobalDashboardFilterService::class)
                ->orderStateOptions()
                ->mapWithKeys(fn (string $state): array => [$state => $state])
                ->all(),
            'countries' => app(GlobalDashboardFilterService::class)
                ->orderCountryOptions()
                ->mapWithKeys(fn (string $country): array => [$country => $country])
                ->all(),
            'currencies' => app(GlobalDashboardFilterService::class)
                ->orderCurrencyOptions()
                ->mapWithKeys(fn (string $currency): array => [strtoupper($currency) => strtoupper($currency)])
                ->all(),
        ];
    }

    public function hasActiveSearchFilters(): bool
    {
        return app(OrderSearchFilterApplicator::class)->hasSearchParams($this->ordersFilterRequest());
    }

    /**
     * @return array{range: string, from: string|null, to: string|null}|null
     */
    public function activeFilterSummary(): ?array
    {
        if ($this->quickRange === 'all_time') {
            return null;
        }

        $options = app(GlobalDashboardFilterService::class)->orderDateRangeOptions();

        return [
            'range' => $options[$this->quickRange] ?? $this->quickRange,
            'from' => filled($this->dateFrom)
                ? Carbon::parse($this->dateFrom)->timezone(config('app.timezone'))->format('d-m-Y H:i')
                : null,
            'to' => filled($this->dateTo)
                ? Carbon::parse($this->dateTo)->timezone(config('app.timezone'))->format('d-m-Y H:i')
                : null,
        ];
    }

    protected function ordersFilterRequest(): Request
    {
        $params = collect(request()->query())
            ->except(['quick_range'])
            ->merge($this->searchFilterQueryParams())
            ->all();

        if ($this->quickRange === 'all_time') {
            unset($params['df_from'], $params['df_to'], $params['df_range'], $params['quick_range']);
        } else {
            $filter = $this->dashboardFilter();
            $params = array_merge($params, $filter->toQueryParams());
        }

        if ($this->activeTab === 'rebill') {
            $params['tab'] = 'rebill';
        } else {
            unset($params['tab']);
        }

        if ($this->showMoreSearchFilters) {
            $params['of_more'] = '1';
        } else {
            unset($params['of_more']);
        }

        return Request::create('/', 'GET', $params);
    }

    /**
     * @return array<string, mixed>
     */
    protected function searchFilterQueryParams(): array
    {
        $params = [
            'of_order_id' => $this->searchOrderId,
            'of_first_name' => $this->searchFirstName,
            'of_last_name' => $this->searchLastName,
            'of_phone' => $this->searchPhone,
            'of_email' => $this->searchEmail,
            'of_company' => $this->searchCompany,
            'of_status' => $this->searchStatus,
            'of_order_type' => $this->searchOrderType,
            'of_campaign' => $this->searchCampaignId,
            'of_customer_id' => $this->searchCustomerId,
            'of_ip' => $this->searchIpAddress,
            'of_payment_status' => $this->searchPaymentStatus,
            'of_state' => $this->searchState,
            'of_country' => $this->searchCountry,
            'of_currency' => $this->searchCurrency,
        ];

        if ($this->searchPhoneExact) {
            $params['of_phone_exact'] = '1';
        }

        if ($this->searchEmailExact) {
            $params['of_email_exact'] = '1';
        }

        return array_filter($params, fn (mixed $value): bool => filled($value));
    }

    protected function orderCount(?OrderType $type = null): int
    {
        $query = Order::query();

        if ($type !== null) {
            $query->where('order_type', $type);
        }

        $request = $this->ordersFilterRequest();

        $query = app(DashboardFilterApplicator::class)->applyToOrders($query, $request);

        return app(OrderSearchFilterApplicator::class)->apply($query, $request)->count();
    }
}
