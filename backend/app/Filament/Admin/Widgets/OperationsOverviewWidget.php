<?php

namespace App\Filament\Admin\Widgets;

use App\Modules\Admin\Services\DashboardMetricsService;
use App\Support\Admin\AdminAuthorization;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class OperationsOverviewWidget extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected static ?int $sort = 1;

    protected ?string $heading = 'Today & month';

    protected ?string $description = 'Live commerce KPIs';

    protected int | string | array $columnSpan = 'full';

    protected int | array | null $columns = 4;

    protected ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        return AdminAuthorization::can(auth()->user(), 'dashboard.view');
    }

    protected function getStats(): array
    {
        $metrics = app(DashboardMetricsService::class)->metrics();
        $currency = $metrics->currency;

        return [
            Stat::make('Today\'s Orders', Number::format($metrics->todayOrders))
                ->description('Orders placed today')
                ->descriptionIcon(Heroicon::OutlinedShoppingCart)
                ->color('primary'),
            Stat::make('Today\'s Revenue', Number::currency($metrics->todayRevenue, $currency))
                ->description('Net captured today')
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->color('success'),
            Stat::make('Monthly Revenue', Number::currency($metrics->monthlyRevenue, $currency))
                ->description(now()->format('F Y'))
                ->descriptionIcon(Heroicon::OutlinedChartBar)
                ->color('success'),
            Stat::make('Active Subscriptions', Number::format($metrics->activeSubscriptions))
                ->description('Currently billing')
                ->descriptionIcon(Heroicon::OutlinedArrowPath)
                ->color('info'),
            Stat::make('Pending Rebill', Number::format($metrics->pendingRebill))
                ->description('Due for charge now')
                ->descriptionIcon(Heroicon::OutlinedClock)
                ->color($metrics->pendingRebill > 0 ? 'warning' : 'gray'),
            Stat::make('Failed Payments', Number::format($metrics->failedPaymentsToday))
                ->description('Today (checkout + billing)')
                ->descriptionIcon(Heroicon::OutlinedXCircle)
                ->color($metrics->failedPaymentsToday > 0 ? 'danger' : 'gray'),
            Stat::make('Pending Fulfillment', Number::format($metrics->pendingFulfillment))
                ->description('Orders awaiting ship')
                ->descriptionIcon(Heroicon::OutlinedTruck)
                ->color($metrics->pendingFulfillment > 0 ? 'warning' : 'gray'),
            Stat::make('Delayed Shipments', Number::format($metrics->delayedShipments))
                ->description('Past scheduled ship date')
                ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                ->color($metrics->delayedShipments > 0 ? 'danger' : 'gray'),
            Stat::make('Refunds Today', Number::format($metrics->refundsToday))
                ->description(Number::currency($metrics->refundsAmountToday, $currency).' refunded')
                ->descriptionIcon(Heroicon::OutlinedReceiptRefund)
                ->color('warning'),
            Stat::make('Chargebacks (MTD)', Number::format($metrics->chargebacksPeriod))
                ->description(Number::currency($metrics->chargebacksAmountPeriod, $currency).' disputed')
                ->descriptionIcon(Heroicon::OutlinedShieldExclamation)
                ->color($metrics->chargebacksPeriod > 0 ? 'danger' : 'gray'),
            Stat::make('Gateway Health', $metrics->gatewaySuccessRate.'%')
                ->description('Success rate this month')
                ->descriptionIcon(Heroicon::OutlinedSignal)
                ->color($metrics->gatewaySuccessRate >= 95 ? 'success' : 'warning'),
        ];
    }
}
