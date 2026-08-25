<?php

namespace App\Filament\Admin\Widgets\Email;

use App\Modules\Email\Services\EmailAnalyticsService;
use App\Support\Admin\AdminAuthorization;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class EmailAnalyticsStatsWidget extends StatsOverviewWidget
{
    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '120s';

    protected int|array|null $columns = [
        'default' => 1,
        'sm' => 2,
        'xl' => 4,
    ];

    public static function canView(): bool
    {
        return AdminAuthorization::can(auth()->user(), 'email.analytics.view');
    }

    protected function getStats(): array
    {
        $data = app(EmailAnalyticsService::class)->dashboard();

        return [
            Stat::make('Emails sent', Number::format((int) $data['emails_sent']))
                ->description('Last 30 days')
                ->descriptionIcon(Heroicon::OutlinedPaperAirplane)
                ->icon(Heroicon::OutlinedPaperAirplane),
            Stat::make('Delivery rate', $data['delivery_rate'].'%')
                ->description('Delivered / sent')
                ->descriptionIcon(Heroicon::OutlinedCheckCircle)
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color($data['delivery_rate'] >= 95 ? 'success' : 'gray'),
            Stat::make('Open rate', $data['open_rate'].'%')
                ->description('Opened / delivered')
                ->descriptionIcon(Heroicon::OutlinedEnvelopeOpen)
                ->icon(Heroicon::OutlinedEnvelopeOpen),
            Stat::make('Click rate', $data['click_rate'].'%')
                ->description('Clicked / opened')
                ->descriptionIcon(Heroicon::OutlinedCursorArrowRays)
                ->icon(Heroicon::OutlinedCursorArrowRays),
            Stat::make('Bounce rate', $data['bounce_rate'].'%')
                ->description('Bounced / sent')
                ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->color($data['bounce_rate'] > 2 ? 'danger' : 'gray'),
            Stat::make('Spam rate', $data['spam_rate'].'%')
                ->description('Marked as spam')
                ->descriptionIcon(Heroicon::OutlinedShieldExclamation)
                ->icon(Heroicon::OutlinedShieldExclamation),
            Stat::make('Failed emails', Number::format((int) $data['failed_emails']))
                ->description('Delivery failures')
                ->descriptionIcon(Heroicon::OutlinedXCircle)
                ->icon(Heroicon::OutlinedXCircle)
                ->color((int) $data['failed_emails'] > 0 ? 'danger' : 'gray'),
            Stat::make('Queue pending', Number::format((int) $data['queue_pending']))
                ->description('Awaiting send')
                ->descriptionIcon(Heroicon::OutlinedQueueList)
                ->icon(Heroicon::OutlinedQueueList),
        ];
    }
}
