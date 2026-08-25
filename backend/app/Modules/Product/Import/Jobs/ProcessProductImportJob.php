<?php

namespace App\Modules\Product\Import\Jobs;

use App\Models\Funnel;
use App\Models\ProductImportBatch;
use App\Modules\Product\Import\Enums\ProductImportStatus;
use App\Modules\Product\Import\Services\ProductImportBatchService;
use App\Modules\Product\Import\Services\ProductImportExecutionService;
use App\Support\Admin\AdminActivityLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessProductImportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly int $batchId,
    ) {}

    public function handle(
        ProductImportBatchService $batchService,
        ProductImportExecutionService $executionService,
    ): void {
        $batch = ProductImportBatch::query()->findOrFail($this->batchId);

        $batch->update([
            'status' => ProductImportStatus::Running,
            'started_at' => now(),
            'failure_message' => null,
        ]);

        try {
            $groupedProducts = $batch->stats['validated_products'] ?? [];

            if ($groupedProducts === []) {
                $batch = $batchService->runValidation($batch);
                $groupedProducts = $batch->stats['validated_products'] ?? [];
            }

            if ($groupedProducts === []) {
                throw new \RuntimeException('No valid products to import.');
            }

            $stats = $executionService->execute($batch, $groupedProducts);

            if ($batch->target_funnel_id !== null) {
                $funnel = Funnel::query()->find($batch->target_funnel_id);

                if ($funnel !== null) {
                    $batchService->assignImportedToFunnel($batch, $funnel, $stats['imported_product_ids']);
                }
            }

            $completedAt = now();
            $durationMs = $batch->started_at !== null
                ? (int) $batch->started_at->diffInMilliseconds($completedAt)
                : null;

            $batch->update([
                'status' => ProductImportStatus::Completed,
                'completed_at' => $completedAt,
                'stats' => array_merge($batch->stats ?? [], $stats, [
                    'duration_ms' => $durationMs,
                ]),
            ]);

            AdminActivityLogger::log(
                module: 'products',
                event: 'import.completed',
                description: sprintf('Product import batch %s completed.', $batch->uuid),
                subject: $batch,
                properties: [
                    'batch_uuid' => $batch->uuid,
                    'stats' => $stats,
                ],
            );
        } catch (Throwable $exception) {
            $batch->update([
                'status' => ProductImportStatus::Failed,
                'failure_message' => $exception->getMessage(),
                'completed_at' => now(),
            ]);

            AdminActivityLogger::log(
                module: 'products',
                event: 'import.failed',
                description: sprintf('Product import batch %s failed.', $batch->uuid),
                subject: $batch,
                properties: [
                    'batch_uuid' => $batch->uuid,
                    'message' => $exception->getMessage(),
                ],
            );

            throw $exception;
        }
    }
}
