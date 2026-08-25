<?php

namespace App\Filament\Admin\Widgets\Analytics;

use App\Filament\Admin\Widgets\Analytics\Concerns\InteractsWithAnalyticsPeriod;
use App\Support\Admin\AdminAuthorization;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class AnalyticsKpiOverviewWidget extends StatsOverviewWidget
{
    use InteractsWithAnalyticsPeriod;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '120s';

    protected int|array|null $columns = [
        'default' => 1,
        'sm' => 2,
        'xl' => 4,
    ];

    public static function canView(): bool
    {
        return AdminAuthorization::can(auth()->user(), 'analytics.view');
    }

    protected function getStats(): array
    {
        $snapshot = $this->analyticsSnapshot();
        $currency = (string) $snapshot['currency'];
        $failedTotal = (int) $snapshot['failed_checkout_count'] + (int) $snapshot['failed_billing_count'];
        $filter = $this->dashboardFilter();
        $urls = $this->dashboardUrls();
        $trend = $snapshot['revenue_trend'] ?? [];
        $revenueSpark = array_map('floatval', array_column($trend, 'revenue'));
        $ordersSpark = array_map('intval', array_column($trend, 'orders'));

        return [
            Stat::make('Net revenue', Number::currency((float) $snapshot['revenue_total'], $currency))
                ->description((int) $snapshot['revenue_count'].' captured payments')
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->descriptionColor('gray')
                ->icon(Heroicon::OutlinedBanknotes)
                ->color('gray')
                ->chart($revenueSpark !== [] ? $revenueSpark : null)
                ->url($urls->orders($filter, ['df_payment' => 'paid'])),
            Stat::make('Orders', Number::format((int) $snapshot['orders_total']))
                ->description(Number::currency((float) $snapshot['orders_net_revenue'], $currency).' net')
                ->descriptionIcon(Heroicon::OutlinedShoppingCart)
                ->descriptionColor('gray')
                ->icon(Heroicon::OutlinedShoppingCart)
                ->color('gray')
                ->chart($ordersSpark !== [] ? $ordersSpark : null)
                ->url($urls->orders($filter)),
            Stat::make('Refunds', Number::currency((float) $snapshot['refund_total'], $currency))
                ->description((int) $snapshot['refund_count'].' refunds')
                ->descriptionIcon(Heroicon::OutlinedReceiptRefund)
                ->descriptionColor('gray')
                ->icon(Heroicon::OutlinedReceiptRefund)
                ->color('gray')
                ->url($urls->orders($filter, ['df_payment' => 'refunded'])),
            Stat::make('Failed payments', Number::format($failedTotal))
                ->description('Checkout + billing failures')
                ->descriptionIcon(Heroicon::OutlinedXCircle)
                ->descriptionColor($failedTotal > 0 ? 'danger' : 'gray')
                ->icon(Heroicon::OutlinedXCircle)
                ->color($failedTotal > 0 ? 'danger' : 'gray')
                ->url($urls->orders($filter, ['df_payment' => 'failed'])),
            Stat::make('MRR', Number::currency((float) $snapshot['mrr'], $currency))
                ->description('ARR '.Number::currency((float) $snapshot['arr'], $currency))
                ->descriptionIcon(Heroicon::OutlinedArrowPath)
                ->descriptionColor('gray')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->url($urls->subscriptions($filter, ['df_status' => 'active'])),
            Stat::make('New subscriptions', Number::format((int) $snapshot['new_subscriptions']))
                ->description((int) $snapshot['cancelled_subscriptions'].' cancelled in period')
                ->descriptionIcon(Heroicon::OutlinedUserPlus)
                ->descriptionColor('gray')
                ->icon(Heroicon::OutlinedUserPlus)
                ->color('gray')
                ->url($urls->subscriptions($filter)),
            Stat::make('Past due', Number::format((int) $snapshot['past_due_subscriptions']))
                ->description('Subscriptions past due')
                ->descriptionIcon(Heroicon::OutlinedExclamationCircle)
                ->descriptionColor((int) $snapshot['past_due_subscriptions'] > 0 ? 'danger' : 'gray')
                ->icon(Heroicon::OutlinedExclamationCircle)
                ->color((int) $snapshot['past_due_subscriptions'] > 0 ? 'danger' : 'gray')
                ->url($urls->subscriptions($filter, ['df_status' => 'past_due'])),
            Stat::make('Gateway success', ((float) $snapshot['gateway_success_rate']).'%')
                ->description('Payment success in selected range')
                ->descriptionIcon(Heroicon::OutlinedSignal)
                ->descriptionColor((float) $snapshot['gateway_success_rate'] >= 95 ? 'success' : 'gray')
                ->icon(Heroicon::OutlinedSignal)
                ->color((float) $snapshot['gateway_success_rate'] >= 95 ? 'success' : 'gray')
                ->url($urls->transactions($filter)),
        ];
    }
}
