<?php

namespace App\Modules\StripeWebhook\Jobs;

use App\Modules\StripeWebhook\Services\StripeWebhookProcessorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessStripeWebhookJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries;

    /** @var list<int> */
    public array $backoff;

    public function __construct(
        public readonly int $webhookEventId,
    ) {
        $this->tries = (int) config('stripe_webhook.job.tries', 5);
        $this->backoff = config('stripe_webhook.job.backoff', [60, 300, 900, 1800, 3600]);
        $this->onQueue((string) config('stripe_webhook.queue', 'default'));
    }

    public function handle(StripeWebhookProcessorService $processor): void
    {
        $processor->process($this->webhookEventId);
    }

    public function failed(Throwable $exception): void
    {
        app(StripeWebhookProcessorService::class)->markPermanentlyFailed(
            $this->webhookEventId,
            $exception->getMessage(),
        );
    }
}
