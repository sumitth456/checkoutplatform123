<?php

namespace App\Filament\Affiliate\Widgets;

use App\Models\Affiliate;
use App\Models\AffiliateClick;
use App\Models\AffiliateCommission;
use App\Models\AffiliateConversion;
use App\Modules\Affiliate\Enums\CommissionStatus;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class AffiliatePortalStatsWidget extends StatsOverviewWidget
{
    protected ?string $heading = 'Performance overview';

    protected int|string|array $columnSpan = 'full';

    protected int|array|null $columns = 3;

    protected ?string $pollingInterval = '120s';

    protected function getStats(): array
    {
        /** @var Affiliate $affiliate */
        $affiliate = auth('affiliate')->user();

        $clicksToday = AffiliateClick::query()
            ->where('affiliate_id', $affiliate->id)
            ->whereDate('clicked_at', today())
            ->count();

        $conversionsToday = AffiliateConversion::query()
            ->where('affiliate_id', $affiliate->id)
            ->whereDate('converted_at', today())
            ->count();

        $revenueToday = (float) AffiliateConversion::query()
            ->where('affiliate_id', $affiliate->id)
            ->whereDate('converted_at', today())
            ->sum('revenue');

        $pendingCommission = (float) AffiliateCommission::query()
            ->where('affiliate_id', $affiliate->id)
            ->where('status', CommissionStatus::Pending)
            ->sum('commission_amount');

        return [
            Stat::make('Total Clicks', Number::format($affiliate->total_clicks))
                ->description(Number::format($clicksToday).' today')
                ->descriptionIcon(Heroicon::OutlinedCursorArrowRays)
                ->color('primary'),
            Stat::make('Conversions', Number::format($affiliate->total_conversions))
                ->description(Number::format($conversionsToday).' today')
                ->descriptionIcon(Heroicon::OutlinedShoppingCart)
                ->color('success'),
            Stat::make('Lifetime Revenue', Number::currency((float) $affiliate->lifetime_revenue, 'USD'))
                ->description(Number::currency($revenueToday, 'USD').' today')
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->color('info'),
            Stat::make('Pending Commission', Number::currency($pendingCommission, 'USD'))
                ->description(Number::currency((float) $affiliate->paid_commission, 'USD').' paid')
                ->descriptionIcon(Heroicon::OutlinedClock)
                ->color('warning'),
        ];
    }
}
