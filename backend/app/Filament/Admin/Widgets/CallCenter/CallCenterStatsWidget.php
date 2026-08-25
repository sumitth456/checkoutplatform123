<?php

namespace App\Filament\Admin\Widgets\CallCenter;

use App\Models\CallCenterAgent;
use App\Models\CallDisposition;
use App\Models\CallLog;
use App\Modules\CallCenter\Enums\AgentStatus;
use App\Modules\CallCenter\Enums\CallStatus;
use App\Support\Admin\AdminAuthorization;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class CallCenterStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $heading = 'Today\'s call center metrics';

    protected ?string $description = 'Live agent and call performance';

    protected int|string|array $columnSpan = 'full';

    protected int|array|null $columns = 4;

    protected ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        return AdminAuthorization::can(auth()->user(), 'call_center.view');
    }

    protected function getStats(): array
    {
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();

        $activeAgents = CallCenterAgent::query()
            ->where('status', AgentStatus::Active)
            ->where('is_logged_in', true)
            ->count();

        $callsToday = CallLog::query()
            ->whereBetween('started_at', [$todayStart, $todayEnd])
            ->count();

        $answered = CallLog::query()
            ->whereBetween('started_at', [$todayStart, $todayEnd])
            ->where('call_status', CallStatus::Answered)
            ->count();

        $missed = CallLog::query()
            ->whereBetween('started_at', [$todayStart, $todayEnd])
            ->where('call_status', CallStatus::Missed)
            ->count();

        $saleDispositionIds = CallDisposition::query()
            ->where('counts_as_sale', true)
            ->pluck('id');

        $sales = CallLog::query()
            ->whereBetween('started_at', [$todayStart, $todayEnd])
            ->whereIn('disposition_id', $saleDispositionIds)
            ->count();

        $conversionRate = $answered > 0
            ? round(($sales / $answered) * 100, 1)
            : 0.0;

        $avgDuration = (int) CallLog::query()
            ->whereBetween('started_at', [$todayStart, $todayEnd])
            ->whereIn('call_status', [CallStatus::Answered, CallStatus::Completed])
            ->avg('duration_seconds');

        $avgMinutes = intdiv(max(0, $avgDuration), 60);
        $avgSeconds = max(0, $avgDuration) % 60;
        $avgCallTime = sprintf('%d:%02d', $avgMinutes, $avgSeconds);

        return [
            Stat::make('Active Agents', Number::format($activeAgents))
                ->description('Logged in and active')
                ->descriptionIcon(Heroicon::OutlinedUserGroup)
                ->color('success'),
            Stat::make('Calls Today', Number::format($callsToday))
                ->description('Total calls started today')
                ->descriptionIcon(Heroicon::OutlinedPhone)
                ->color('primary'),
            Stat::make('Answered', Number::format($answered))
                ->description('Calls answered today')
                ->descriptionIcon(Heroicon::OutlinedPhoneArrowUpRight)
                ->color('success'),
            Stat::make('Missed', Number::format($missed))
                ->description('Missed calls today')
                ->descriptionIcon(Heroicon::OutlinedPhoneXMark)
                ->color($missed > 0 ? 'danger' : 'gray'),
            Stat::make('Sales', Number::format($sales))
                ->description('Sale dispositions today')
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->color('success'),
            Stat::make('Conversion Rate', $conversionRate.'%')
                ->description('Sales / answered calls')
                ->descriptionIcon(Heroicon::OutlinedChartBar)
                ->color($conversionRate >= 10 ? 'success' : 'warning'),
            Stat::make('Avg Call Time', $avgCallTime)
                ->description('Average duration today')
                ->descriptionIcon(Heroicon::OutlinedClock)
                ->color('info'),
        ];
    }
}
