<?php

namespace App\Modules\Admin\Services;

use App\Models\AdminActivityLog;
use App\Models\CheckoutPaymentFailure;
use App\Models\StripeWebhookEvent;
use App\Modules\StripeWebhook\Enums\StripeWebhookProcessingStatus;

final class AdminLogService
{
    /**
     * @return array{all: int, stripe: int}
     */
    public function paymentFailureTabCounts(): array
    {
        return [
            'all' => CheckoutPaymentFailure::query()->count(),
            'stripe' => CheckoutPaymentFailure::query()->where('provider', 'stripe')->count(),
        ];
    }

    /**
     * @return array{all: int, pending: int, processed: int, failed: int}
     */
    public function webhookEventTabCounts(): array
    {
        return [
            'all' => StripeWebhookEvent::query()->count(),
            'pending' => StripeWebhookEvent::query()->whereIn('status', [
                StripeWebhookProcessingStatus::Pending,
                StripeWebhookProcessingStatus::Processing,
            ])->count(),
            'processed' => StripeWebhookEvent::query()
                ->where('status', StripeWebhookProcessingStatus::Processed)
                ->count(),
            'failed' => StripeWebhookEvent::query()->whereIn('status', [
                StripeWebhookProcessingStatus::Failed,
                StripeWebhookProcessingStatus::Duplicate,
            ])->count(),
        ];
    }

    /**
     * @return list<string>
     */
    public function adminActivityModules(): array
    {
        return AdminActivityLog::query()
            ->whereNotNull('module')
            ->where('module', '!=', '')
            ->distinct()
            ->orderBy('module')
            ->pluck('module')
            ->map(fn (mixed $module): string => (string) $module)
            ->values()
            ->all();
    }

    /**
     * @return array<string, int>
     */
    public function adminActivityTabCounts(): array
    {
        $counts = [
            'all' => AdminActivityLog::query()->count(),
        ];

        foreach ($this->adminActivityModules() as $module) {
            $counts[$module] = AdminActivityLog::query()->where('module', $module)->count();
        }

        return $counts;
    }
}
