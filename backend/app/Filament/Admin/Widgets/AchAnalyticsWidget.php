<?php

namespace App\Filament\Admin\Widgets;

use App\Modules\Ach\Services\AchAnalyticsService;
use App\Support\Admin\AdminAuthorization;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class AchAnalyticsWidget extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected static ?int $sort = 5;

    protected ?string $heading = 'ACH payments';

    protected ?string $description = 'Bank transfer performance';

    protected int | string | array $columnSpan = 'full';

    protected int | array | null $columns = 4;

    protected ?string $pollingInterval = '120s';

    public static function canView(): bool
    {
        return AdminAuthorization::can(auth()->user(), 'dashboard.view');
    }

    protected function getStats(): array
    {
        $metrics = app(AchAnalyticsService::class)->metrics();

        return [
            Stat::make('ACH Revenue', Number::currency($metrics['ach_revenue'], 'USD'))
                ->description('Captured ACH volume')
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->color('success'),
            Stat::make('ACH Orders', Number::format($metrics['ach_orders']))
                ->description('Orders paid by bank transfer')
                ->descriptionIcon(Heroicon::OutlinedShoppingCart)
                ->color('primary'),
            Stat::make('ACH Refunds', Number::format($metrics['ach_refunds']))
                ->description(Number::currency($metrics['ach_refund_amount'], 'USD').' refunded')
                ->descriptionIcon(Heroicon::OutlinedReceiptRefund)
                ->color('warning'),
            Stat::make('ACH Success Rate', $metrics['ach_success_rate'].'%')
                ->description('Capture success rate')
                ->descriptionIcon(Heroicon::OutlinedSignal)
                ->color($metrics['ach_success_rate'] >= 90 ? 'success' : 'warning'),
            Stat::make('ACH Failed Payments', Number::format($metrics['ach_failed_payments']))
                ->description('Declined or failed captures')
                ->descriptionIcon(Heroicon::OutlinedXCircle)
                ->color($metrics['ach_failed_payments'] > 0 ? 'danger' : 'gray'),
            Stat::make('ACH Rebill Success', Number::format($metrics['ach_rebill_success']))
                ->description('Successful subscription rebills')
                ->descriptionIcon(Heroicon::OutlinedArrowPath)
                ->color('info'),
        ];
    }
}
