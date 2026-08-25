<?php

namespace App\Modules\Email\Jobs;

use App\Models\EmailQueueItem;
use App\Modules\Email\Enums\EmailQueueStatus;
use App\Modules\Email\Services\EmailDispatchService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class ProcessEmailQueueItemJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(
        public readonly int $queueItemId,
    ) {
        $this->onQueue((string) config('email.queue', 'default'));
    }

    public function handle(EmailDispatchService $dispatch): void
    {
        $item = EmailQueueItem::query()->find($this->queueItemId);

        if ($item === null) {
            return;
        }

        if (in_array($item->status, [EmailQueueStatus::Sent, EmailQueueStatus::Cancelled], true)) {
            return;
        }

        if ($item->scheduled_at !== null && $item->scheduled_at->isFuture()) {
            return;
        }

        $dispatch->processQueueItem($item);
    }
}
