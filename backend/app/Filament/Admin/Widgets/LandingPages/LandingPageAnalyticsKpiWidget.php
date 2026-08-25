<?php

namespace App\Filament\Admin\Widgets\LandingPages;

use App\Filament\Admin\Widgets\LandingPages\Concerns\InteractsWithLandingPageAnalyticsPeriod;
use App\Support\Admin\AdminAuthorization;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class LandingPageAnalyticsKpiWidget extends StatsOverviewWidget
{
    use InteractsWithLandingPageAnalyticsPeriod;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '120s';

    protected int|array|null $columns = 4;

    public static function canView(): bool
    {
        return AdminAuthorization::can(auth()->user(), 'landing_pages.view');
    }

    protected function getStats(): array
    {
        $snapshot = $this->landingPageAnalyticsSnapshot();

        return [
            Stat::make('Page views', Number::format((int) $snapshot['views']))
                ->description('Unique view events in range')
                ->descriptionIcon(Heroicon::OutlinedEye)
                ->color('primary'),
            Stat::make('Conversions', Number::format((int) $snapshot['conversions']))
                ->description('Paid orders with landing page attribution')
                ->descriptionIcon(Heroicon::OutlinedShoppingCart)
                ->color('success'),
            Stat::make('Revenue', Number::currency((float) $snapshot['revenue'], 'USD'))
                ->description('Attributed order revenue')
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->color('success'),
            Stat::make('Conversion rate', ((float) $snapshot['conversion_rate']).'%')
                ->description('Conversions ÷ views')
                ->descriptionIcon(Heroicon::OutlinedArrowTrendingUp)
                ->color((float) $snapshot['conversion_rate'] >= 2 ? 'success' : 'gray'),
        ];
    }
}
