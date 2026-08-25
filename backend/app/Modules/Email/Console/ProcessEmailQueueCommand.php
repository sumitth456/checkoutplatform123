<?php

namespace App\Modules\Email\Console;

use App\Models\EmailQueueItem;
use App\Modules\Email\Enums\EmailQueueStatus;
use App\Modules\Email\Jobs\ProcessEmailQueueItemJob;
use Illuminate\Console\Command;

final class ProcessEmailQueueCommand extends Command
{
    protected $signature = 'email:process-queue {--limit= : Maximum items to dispatch}';

    protected $description = 'Dispatch due transactional email queue items for processing';

    public function handle(): int
    {
        $limit = (int) ($this->option('limit') ?: config('email.process_batch_size', 50));

        $items = EmailQueueItem::query()
            ->whereIn('status', [
                EmailQueueStatus::Pending,
                EmailQueueStatus::Queued,
                EmailQueueStatus::Retry,
            ])
            ->where(function ($query): void {
                $query->whereNull('scheduled_at')
                    ->orWhere('scheduled_at', '<=', now());
            })
            ->orderBy('scheduled_at')
            ->limit($limit)
            ->pluck('id');

        foreach ($items as $id) {
            ProcessEmailQueueItemJob::dispatch($id);
        }

        $this->info("Dispatched {$items->count()} email queue item(s).");

        return self::SUCCESS;
    }
}
