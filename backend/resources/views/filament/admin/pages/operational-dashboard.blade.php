@php
    $m = $metrics ?? [];
    $currency = $currency ?? 'USD';
    $sales = $m['sales'] ?? [];
    $kpis = $m['kpis'] ?? [];
    $period = $m['period'] ?? [];
    $subscriptions = $m['subscriptions'] ?? [];
    $customersMetric = $m['customers'] ?? ['value' => 0, 'url' => '#'];

    $totalOrders = (int) ($sales['total_orders']['value'] ?? $kpis['orders']['value'] ?? 0);
    $rebillOrders = (int) ($sales['rebill_orders']['value'] ?? 0);
    $initialOrders = max(0, $totalOrders - $rebillOrders);

    $formatValue = function (mixed $value, string $format) use ($currency): string {
        if ($value === null || $value === '') {
            return '0';
        }

        return match ($format) {
            'currency' => '$' . number_format((float) $value, 2),
            'percent' => number_format((float) $value, 1) . '%',
            default => number_format((float) $value),
        };
    };

    $primaryKpis = [
        [
            'label' => 'Total Orders',
            'icon' => 'shopping-cart',
            'value' => $totalOrders,
            'format' => 'number',
            'url' => $sales['total_orders']['url'] ?? ($kpis['orders']['url'] ?? '#'),
            'change' => $kpis['orders']['change'] ?? null,
        ],
        [
            'label' => 'Initial Orders',
            'icon' => 'shopping-bag',
            'value' => $initialOrders,
            'format' => 'number',
            'url' => $sales['total_orders']['url'] ?? '#',
            'change' => null,
        ],
        [
            'label' => 'Rebill Orders',
            'icon' => 'arrow-path',
            'value' => $rebillOrders,
            'format' => 'number',
            'url' => $sales['rebill_orders']['url'] ?? '#',
            'change' => null,
        ],
        [
            'label' => 'Total Revenue',
            'icon' => 'banknotes',
            'value' => $kpis['gross_revenue']['value'] ?? ($period['revenue_total'] ?? 0),
            'format' => 'currency',
            'url' => $kpis['gross_revenue']['url'] ?? '#',
            'change' => $kpis['gross_revenue']['change'] ?? null,
        ],
        [
            'label' => 'Net Revenue',
            'icon' => 'currency-dollar',
            'value' => $kpis['net_revenue']['value'] ?? ($period['orders_net_revenue'] ?? 0),
            'format' => 'currency',
            'url' => $kpis['net_revenue']['url'] ?? '#',
            'change' => $kpis['net_revenue']['change'] ?? null,
        ],
        [
            'label' => 'Successful Payments',
            'icon' => 'check-circle',
            'value' => $sales['approved']['value'] ?? 0,
            'format' => 'number',
            'url' => $sales['approved']['url'] ?? '#',
            'change' => null,
        ],
        [
            'label' => 'Failed Payments',
            'icon' => 'x-circle',
            'value' => $sales['declined']['value'] ?? ($period['failed_checkout_count'] ?? 0) + ($period['failed_billing_count'] ?? 0),
            'format' => 'number',
            'url' => $sales['declined']['url'] ?? '#',
            'change' => null,
        ],
        [
            'label' => 'Refunds',
            'icon' => 'receipt-refund',
            'value' => $sales['refunds']['value'] ?? ($period['refund_count'] ?? 0),
            'format' => 'number',
            'url' => $sales['refunds']['url'] ?? '#',
            'change' => null,
        ],
        [
            'label' => 'New Subscriptions',
            'icon' => 'plus-circle',
            'value' => $period['new_subscriptions'] ?? 0,
            'format' => 'number',
            'url' => $subscriptions['active']['url'] ?? '#',
            'change' => null,
        ],
        [
            'label' => 'Active Subscriptions',
            'icon' => 'arrow-path-rounded-square',
            'value' => $subscriptions['active']['value'] ?? 0,
            'format' => 'number',
            'url' => $subscriptions['active']['url'] ?? '#',
            'change' => null,
        ],
        [
            'label' => 'MRR',
            'icon' => 'chart-bar',
            'value' => $period['mrr'] ?? 0,
            'format' => 'currency',
            'url' => $subscriptions['active']['url'] ?? '#',
            'change' => null,
        ],
        [
            'label' => 'Customers',
            'icon' => 'users',
            'value' => $customersMetric['value'] ?? 0,
            'format' => 'number',
            'url' => $customersMetric['url'] ?? '#',
            'change' => null,
        ],
    ];

    $performanceKpis = [
        'aov' => ['label' => 'Average order value', 'icon' => 'receipt-percent'],
        'rebill_revenue' => ['label' => 'Rebill revenue', 'icon' => 'arrow-path'],
        'subscription_revenue' => ['label' => 'Subscription revenue', 'icon' => 'arrow-path-rounded-square'],
        'conversion_rate' => ['label' => 'Conversion rate', 'icon' => 'cursor-arrow-rays'],
        'approval_rate' => ['label' => 'Approval rate', 'icon' => 'check-badge'],
    ];

    $summarySections = [
        'sales' => [
            'title' => 'Sales Summary',
            'icon' => 'shopping-bag',
            'items' => [
                'total_orders' => 'Total orders',
                'approved' => 'Approved orders',
                'declined' => 'Declined orders',
                'pending' => 'Pending orders',
                'refunds' => 'Refunds',
                'chargebacks' => 'Chargebacks',
                'upsells' => 'Upsells',
                'order_bumps' => 'Order bumps',
                'subscription_orders' => 'Subscription orders',
                'rebill_orders' => 'Rebill orders',
            ],
        ],
        'customer_service' => [
            'title' => 'Customer Service',
            'icon' => 'lifebuoy',
            'items' => [
                'refund_requests' => 'Refund requests',
                'refund_completed' => 'Refund completed',
                'partial_refunds' => 'Partial refunds',
                'chargebacks' => 'Chargebacks',
                'cancellations' => 'Cancellations',
                'tickets' => 'Customer tickets',
                'pending_tickets' => 'Pending tickets',
            ],
        ],
        'fulfillment' => [
            'title' => 'Fulfillment',
            'icon' => 'truck',
            'items' => [
                'pending' => 'Pending fulfillment',
                'sent' => 'Sent to fulfillment',
                'packed' => 'Packed',
                'shipped' => 'Shipped',
                'delivered' => 'Delivered',
                'returned' => 'Returned',
                'cancelled' => 'Cancelled',
                'failed' => 'Failed',
                'rma_pending' => 'RMA pending',
            ],
        ],
        'subscriptions' => [
            'title' => 'Subscriptions',
            'icon' => 'arrow-path-rounded-square',
            'items' => [
                'active' => 'Active subscriptions',
                'paused' => 'Paused',
                'cancelled' => 'Cancelled',
                'expired' => 'Expired',
                'upcoming_rebills' => 'Upcoming rebills',
                'successful_rebills' => 'Successful rebills',
                'failed_rebills' => 'Failed rebills',
                'retry_queue' => 'Retry queue',
            ],
        ],
        'affiliates' => [
            'title' => 'Affiliates',
            'icon' => 'user-group',
            'items' => [
                'clicks' => 'Clicks',
                'visitors' => 'Visitors',
                'conversions' => 'Conversions',
                'conversion_rate' => 'Conversion rate',
                'commission' => 'Commission',
                'pending_payout' => 'Pending payout',
                'paid_commission' => 'Paid commission',
            ],
        ],
    ];

    $chartTitles = [
        'revenue' => 'Revenue Trend',
        'orders' => 'Orders Trend',
        'subscriptions' => 'Subscription Growth',
        'rebills' => 'Rebill Revenue Trend',
    ];

    $topLists = [
        'campaigns' => 'Top Campaigns',
        'products' => 'Top Products',
        'affiliates' => 'Top Affiliates',
        'gateways' => 'Top Gateways',
        'traffic_sources' => 'Top Traffic Sources',
        'countries' => 'Top Countries',
        'states' => 'Top States',
        'cities' => 'Top Cities',
    ];
@endphp

<div class="cp-dashboard col-span-full" wire:loading.class="cp-dashboard--loading">
    <div wire:loading.flex wire:target="applyFilters,quickRange" class="cp-dashboard__overlay">
        <div class="cp-dashboard__spinner"></div>
    </div>

    {{-- Primary KPI row --}}
    <section class="cp-dashboard-section">
        <header class="cp-dashboard-section__header">
            <h2 class="cp-dashboard-section__title">Key metrics</h2>
            <p class="cp-dashboard-section__subtitle">Summary for the selected date range</p>
        </header>
        <div class="cp-dashboard-kpis cp-dashboard-kpis--primary">
            @foreach ($primaryKpis as $kpi)
                <a href="{{ $kpi['url'] }}" wire:navigate class="cp-dashboard-kpi cp-dashboard-kpi--compact">
                    <div class="cp-dashboard-kpi__head">
                        <x-filament::icon :icon="'heroicon-o-' . $kpi['icon']" class="cp-dashboard-kpi__icon" />
                        <span class="cp-dashboard-kpi__label">{{ $kpi['label'] }}</span>
                    </div>
                    <div class="cp-dashboard-kpi__value">{{ $formatValue($kpi['value'], $kpi['format']) }}</div>
                    @if ($kpi['change'] !== null)
                        <div @class([
                            'cp-dashboard-kpi__change',
                            'cp-dashboard-kpi__change--up' => $kpi['change'] >= 0,
                            'cp-dashboard-kpi__change--down' => $kpi['change'] < 0,
                        ])>
                            {{ $kpi['change'] >= 0 ? '+' : '' }}{{ number_format($kpi['change'], 1) }}% vs prior period
                        </div>
                    @endif
                </a>
            @endforeach
        </div>
    </section>

    {{-- Performance KPIs (existing metrics, repositioned) --}}
    @if (! empty($kpis))
        <section class="cp-dashboard-section">
            <header class="cp-dashboard-section__header">
                <h2 class="cp-dashboard-section__title">Performance</h2>
            </header>
            <div class="cp-dashboard-kpis cp-dashboard-kpis--secondary">
                @foreach ($performanceKpis as $key => $meta)
                    @php $kpi = $kpis[$key] ?? null; @endphp
                    @if ($kpi)
                        <a href="{{ $kpi['url'] }}" wire:navigate class="cp-dashboard-kpi cp-dashboard-kpi--compact">
                            <div class="cp-dashboard-kpi__head">
                                <x-filament::icon :icon="'heroicon-o-' . $meta['icon']" class="cp-dashboard-kpi__icon" />
                                <span class="cp-dashboard-kpi__label">{{ $meta['label'] }}</span>
                            </div>
                            <div class="cp-dashboard-kpi__value">{{ $formatValue($kpi['value'], $kpi['format']) }}</div>
                            @if (($kpi['change'] ?? null) !== null)
                                <div @class([
                                    'cp-dashboard-kpi__change',
                                    'cp-dashboard-kpi__change--up' => $kpi['change'] >= 0,
                                    'cp-dashboard-kpi__change--down' => $kpi['change'] < 0,
                                ])>
                                    {{ $kpi['change'] >= 0 ? '+' : '' }}{{ number_format($kpi['change'], 1) }}%
                                </div>
                            @endif
                            @if (! empty($kpi['sparkline']))
                                @include('filament.admin.partials.dashboard-sparkline', ['points' => $kpi['sparkline']])
                            @endif
                        </a>
                    @endif
                @endforeach
            </div>
        </section>
    @endif

    {{-- Summary sections --}}
    <section class="cp-dashboard-section">
        <header class="cp-dashboard-section__header">
            <h2 class="cp-dashboard-section__title">Operations breakdown</h2>
        </header>
        <div class="cp-dashboard-summaries">
            @foreach ($summarySections as $sectionKey => $section)
                <div class="cp-dashboard-summary">
                    <header class="cp-dashboard-summary__header">
                        <x-filament::icon :icon="'heroicon-o-' . $section['icon']" class="cp-dashboard-summary__icon" />
                        <h3>{{ $section['title'] }}</h3>
                    </header>
                    <div class="cp-dashboard-summary__grid">
                        @foreach ($section['items'] as $itemKey => $itemLabel)
                            @php $item = $m[$sectionKey][$itemKey] ?? null; @endphp
                            @if ($item)
                                <a href="{{ $item['url'] }}" wire:navigate class="cp-dashboard-metric">
                                    <span class="cp-dashboard-metric__label">{{ $itemLabel }}</span>
                                    <span class="cp-dashboard-metric__value">
                                        @if (is_float($item['value']) && (str_contains($itemKey, 'commission') || str_contains($itemKey, 'payout')))
                                            ${{ number_format($item['value'], 2) }}
                                        @elseif (str_contains($itemKey, 'rate'))
                                            {{ number_format((float) $item['value'], 1) }}%
                                        @else
                                            {{ number_format((float) $item['value']) }}
                                        @endif
                                    </span>
                                </a>
                            @else
                                <div class="cp-dashboard-metric cp-dashboard-metric--empty">
                                    <span class="cp-dashboard-metric__label">{{ $itemLabel }}</span>
                                    <span class="cp-dashboard-metric__value">No data</span>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endforeach

            {{-- Gateway summary --}}
            <div class="cp-dashboard-summary cp-dashboard-summary--wide">
                <header class="cp-dashboard-summary__header">
                    <x-filament::icon icon="heroicon-o-credit-card" class="cp-dashboard-summary__icon" />
                    <h3>Gateway Summary</h3>
                </header>
                @if (empty($m['gateways']))
                    <p class="cp-empty-state">No gateway transactions in this period.</p>
                @else
                    <div class="cp-dashboard-gateways">
                        @foreach ($m['gateways'] as $gateway)
                            <a href="{{ $gateway['url'] }}" wire:navigate class="cp-dashboard-gateway">
                                <div class="cp-dashboard-gateway__name">{{ $gateway['name'] }}</div>
                                <div class="cp-dashboard-gateway__stats">
                                    <span><strong>{{ number_format($gateway['transactions']) }}</strong> txns</span>
                                    <span><strong>{{ number_format($gateway['approved']) }}</strong> approved</span>
                                    <span><strong>{{ number_format($gateway['declined']) }}</strong> declined</span>
                                    <span class="cp-badge cp-badge--success">{{ $gateway['approval_rate'] }}% rate</span>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </section>

    {{-- Charts --}}
    <section class="cp-dashboard-section">
        <header class="cp-dashboard-section__header">
            <h2 class="cp-dashboard-section__title">Trends</h2>
        </header>
        <div class="cp-dashboard-charts">
            @foreach ($chartTitles as $chartKey => $chartTitle)
                @php $series = $m['charts'][$chartKey] ?? []; @endphp
                <div class="cp-dashboard-chart">
                    <h3 class="cp-dashboard-chart__title">{{ $chartTitle }}</h3>
                    @if (empty($series))
                        <p class="cp-empty-state">No data for selected filters.</p>
                    @else
                        @include('filament.admin.partials.dashboard-line-chart', ['series' => $series, 'id' => $chartKey])
                    @endif
                </div>
            @endforeach
        </div>
    </section>

    {{-- Top lists --}}
    <section class="cp-dashboard-section">
        <header class="cp-dashboard-section__header">
            <h2 class="cp-dashboard-section__title">Top performers</h2>
        </header>
        <div class="cp-dashboard-tops">
            @foreach ($topLists as $listKey => $listTitle)
                @php $items = $m['tops'][$listKey] ?? []; @endphp
                <div class="cp-dashboard-top">
                    <h3 class="cp-dashboard-top__title">{{ $listTitle }}</h3>
                    @if (empty($items))
                        <p class="cp-empty-state">No data yet.</p>
                    @else
                        <ol class="cp-dashboard-top__list">
                            @foreach ($items as $item)
                                <li>
                                    <a href="{{ $item['url'] }}" wire:navigate class="cp-dashboard-top__item">
                                        <span class="cp-dashboard-top__name">{{ $item['name'] }}</span>
                                        <span class="cp-dashboard-top__value">
                                            @if (isset($item['orders']))
                                                {{ number_format($item['orders']) }} orders · ${{ number_format($item['value'], 2) }}
                                            @elseif (is_float($item['value']))
                                                ${{ number_format($item['value'], 2) }}
                                            @else
                                                {{ number_format($item['value']) }}
                                            @endif
                                        </span>
                                    </a>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </div>
            @endforeach
        </div>
    </section>
</div>
