<?php

namespace App\Modules\Email\Services;

use App\Models\EmailLog;
use App\Models\EmailQueueItem;
use App\Models\EmailTemplate;
use App\Modules\Email\Enums\EmailLogStatus;
use App\Modules\Email\Enums\EmailQueueStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

final class EmailAnalyticsService
{
    /**
     * @return array<string, float|int|string>
     */
    public function dashboard(?Carbon $from = null, ?Carbon $to = null): array
    {
        if (! Schema::hasTable('email_logs')) {
            return $this->emptyDashboard();
        }

        $from ??= now()->subDays(30)->startOfDay();
        $to ??= now()->endOfDay();

        $logs = EmailLog::query()->whereBetween('created_at', [$from, $to]);
        $sent = (clone $logs)->where('status', EmailLogStatus::Sent)->count();
        $delivered = (clone $logs)->whereNotNull('delivered_at')->count();
        $opened = (clone $logs)->whereNotNull('opened_at')->count();
        $clicked = (clone $logs)->whereNotNull('clicked_at')->count();
        $bounced = (clone $logs)->whereNotNull('bounced_at')->count();
        $spam = (clone $logs)->whereNotNull('spam_at')->count();
        $failed = (clone $logs)->where('status', EmailLogStatus::Failed)->count();

        $queuePending = EmailQueueItem::query()
            ->whereIn('status', [
                EmailQueueStatus::Pending,
                EmailQueueStatus::Queued,
                EmailQueueStatus::Retry,
                EmailQueueStatus::Processing,
            ])
            ->count();

        $topTemplates = EmailTemplate::query()
            ->withCount(['logs as sent_count' => function ($query) use ($from, $to): void {
                $query->whereBetween('email_logs.created_at', [$from, $to])
                    ->where('status', EmailLogStatus::Sent);
            }])
            ->having('sent_count', '>', 0)
            ->orderByDesc('sent_count')
            ->limit(5)
            ->get()
            ->map(fn (EmailTemplate $template): array => [
                'template' => $template->name,
                'count' => (int) $template->sent_count,
            ])
            ->all();

        return [
            'emails_sent' => $sent,
            'delivery_rate' => $this->rate($delivered, max(1, $sent)),
            'open_rate' => $this->rate($opened, max(1, $delivered ?: $sent)),
            'click_rate' => $this->rate($clicked, max(1, $opened ?: $delivered ?: $sent)),
            'bounce_rate' => $this->rate($bounced, max(1, $sent)),
            'spam_rate' => $this->rate($spam, max(1, $sent)),
            'failed_emails' => $failed,
            'queue_pending' => $queuePending,
            'top_templates' => $topTemplates,
            'provider_performance' => $this->providerPerformance($from, $to),
            'revenue_generated' => 0,
        ];
    }

    /**
     * @return list<array{provider: string, sent: int, failed: int}>
     */
    private function providerPerformance(Carbon $from, Carbon $to): array
    {
        return EmailLog::query()
            ->selectRaw('email_provider_id, status, count(*) as total')
            ->whereBetween('created_at', [$from, $to])
            ->whereNotNull('email_provider_id')
            ->groupBy('email_provider_id', 'status')
            ->with('provider:id,name')
            ->get()
            ->groupBy('email_provider_id')
            ->map(function ($group): array {
                $provider = $group->first()?->provider;

                return [
                    'provider' => $provider?->name ?? 'Unknown',
                    'sent' => (int) $group->where('status', EmailLogStatus::Sent)->sum('total'),
                    'failed' => (int) $group->where('status', EmailLogStatus::Failed)->sum('total'),
                ];
            })
            ->values()
            ->all();
    }

    private function rate(int $numerator, int $denominator): string
    {
        return number_format(($numerator / max(1, $denominator)) * 100, 1);
    }

    /**
     * @return array<string, float|int|string|array>
     */
    private function emptyDashboard(): array
    {
        return [
            'emails_sent' => 0,
            'delivery_rate' => '0.0',
            'open_rate' => '0.0',
            'click_rate' => '0.0',
            'bounce_rate' => '0.0',
            'spam_rate' => '0.0',
            'failed_emails' => 0,
            'queue_pending' => 0,
            'top_templates' => [],
            'provider_performance' => [],
            'revenue_generated' => 0,
        ];
    }
}
