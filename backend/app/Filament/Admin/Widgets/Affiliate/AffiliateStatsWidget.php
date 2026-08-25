<?php

namespace App\Filament\Admin\Widgets\Affiliate;

use App\Models\Affiliate;
use App\Models\AffiliateClick;
use App\Models\AffiliateCommission;
use App\Models\AffiliateConversion;
use App\Models\AffiliatePayout;
use App\Modules\Affiliate\Enums\AffiliateStatus;
use App\Modules\Affiliate\Enums\CommissionStatus;
use App\Modules\Affiliate\Enums\PayoutStatus;
use App\Support\Admin\AdminAuthorization;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class AffiliateStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $heading = 'Affiliate program overview';

    protected ?string $description = 'Key affiliate metrics';

    protected int|string|array $columnSpan = 'full';

    protected int|array|null $columns = 4;

    protected ?string $pollingInterval = '120s';

    public static function canView(): bool
    {
        return AdminAuthorization::can(auth()->user(), 'affiliate_dashboard.view');
    }

    protected function getStats(): array
    {
        $activeAffiliates = Affiliate::query()->where('status', AffiliateStatus::Active)->count();
        $pendingAffiliates = Affiliate::query()->where('status', AffiliateStatus::Pending)->count();

        $clicksToday = AffiliateClick::query()
            ->whereDate('clicked_at', today())
            ->count();

        $conversionsToday = AffiliateConversion::query()
            ->whereDate('converted_at', today())
            ->count();

        $revenueToday = (float) AffiliateConversion::query()
            ->whereDate('converted_at', today())
            ->sum('revenue');

        $pendingCommissions = (float) AffiliateCommission::query()
            ->where('status', CommissionStatus::Pending)
            ->sum('commission_amount');

        $pendingPayouts = (float) AffiliatePayout::query()
            ->where('status', PayoutStatus::Pending)
            ->sum('amount');

        $conversionRate = $clicksToday > 0
            ? round(($conversionsToday / $clicksToday) * 100, 1)
            : 0.0;

        return [
            Stat::make('Active Affiliates', Number::format($activeAffiliates))
                ->description(Number::format($pendingAffiliates).' pending approval')
                ->descriptionIcon(Heroicon::OutlinedUserGroup)
                ->color('success'),
            Stat::make('Clicks Today', Number::format($clicksToday))
                ->description('Unique tracking clicks')
                ->descriptionIcon(Heroicon::OutlinedCursorArrowRays)
                ->color('primary'),
            Stat::make('Conversions Today', Number::format($conversionsToday))
                ->description('Conversion rate '.$conversionRate.'%')
                ->descriptionIcon(Heroicon::OutlinedShoppingCart)
                ->color('success'),
            Stat::make('Revenue Today', Number::currency($revenueToday, 'USD'))
                ->description('Attributed order revenue')
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->color('info'),
            Stat::make('Pending Commissions', Number::currency($pendingCommissions, 'USD'))
                ->description('Awaiting approval or payout')
                ->descriptionIcon(Heroicon::OutlinedClock)
                ->color('warning'),
            Stat::make('Pending Payouts', Number::currency($pendingPayouts, 'USD'))
                ->description('Payout requests in queue')
                ->descriptionIcon(Heroicon::OutlinedCreditCard)
                ->color($pendingPayouts > 0 ? 'warning' : 'gray'),
        ];
    }
}
