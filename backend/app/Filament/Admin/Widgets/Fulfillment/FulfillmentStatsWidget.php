<?php

namespace App\Filament\Admin\Widgets\Fulfillment;

use App\Models\Fulfillment;
use App\Modules\Order\Enums\FulfillmentRecordStatus;
use App\Support\Admin\AdminAuthorization;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class FulfillmentStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $heading = 'Fulfillment KPIs';

    protected ?string $description = 'Pending, shipped, delayed, and returned counts';

    protected int|string|array $columnSpan = 'full';

    protected int|array|null $columns = 4;

    protected ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        return AdminAuthorization::can(auth()->user(), 'fulfillment.view')
            || AdminAuthorization::can(auth()->user(), 'delay_fulfillment.view');
    }

    protected function getStats(): array
    {
        $today = now()->toDateString();

        $pending = Fulfillment::query()
            ->where('status', FulfillmentRecordStatus::Scheduled)
            ->whereDate('scheduled_ship_date', '>=', $today)
            ->count();

        $delayed = Fulfillment::query()
            ->where('status', FulfillmentRecordStatus::Scheduled)
            ->whereDate('scheduled_ship_date', '<', $today)
            ->count();

        $shipped = Fulfillment::query()
            ->whereIn('status', [
                FulfillmentRecordStatus::Submitted,
                FulfillmentRecordStatus::Shipped,
                FulfillmentRecordStatus::Delivered,
            ])
            ->count();

        $returned = Fulfillment::query()
            ->where('status', FulfillmentRecordStatus::Returned)
            ->count();

        return [
            Stat::make('Pending', Number::format($pending))
                ->description('Scheduled, not yet due')
                ->descriptionIcon(Heroicon::OutlinedClock)
                ->color('warning'),
            Stat::make('Delayed', Number::format($delayed))
                ->description('Past scheduled ship date')
                ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                ->color($delayed > 0 ? 'danger' : 'gray'),
            Stat::make('Shipped / In transit', Number::format($shipped))
                ->description('Submitted or shipped')
                ->descriptionIcon(Heroicon::OutlinedTruck)
                ->color('success'),
            Stat::make('Returned', Number::format($returned))
                ->description('Marked as returned')
                ->descriptionIcon(Heroicon::OutlinedArrowUturnLeft)
                ->color($returned > 0 ? 'danger' : 'gray'),
        ];
    }
}
