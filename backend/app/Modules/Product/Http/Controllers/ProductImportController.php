<?php

namespace App\Modules\Product\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ProductImportBatch;
use App\Modules\Product\Import\Services\ProductCatalogExportService;
use App\Modules\Product\Import\Services\ProductImportBatchService;
use App\Support\Admin\AdminAuthorization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductImportController extends Controller
{
    public function __construct(
        private readonly ProductImportBatchService $imports,
    ) {
        $this->middleware('auth:sanctum');
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', ProductImportBatch::class);

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:51200'],
            'target_funnel_id' => ['nullable', 'integer', 'exists:funnels,id'],
        ]);

        $batch = $this->imports->createBatchFromUpload(
            file: $validated['file'],
            userId: (int) $request->user()->id,
            targetFunnelId: isset($validated['target_funnel_id']) ? (int) $validated['target_funnel_id'] : null,
        );

        return response()->json($this->batchPayload($batch), 201);
    }

    public function show(ProductImportBatch $batch): JsonResponse
    {
        $this->authorize('view', $batch);

        $batch->loadCount('errors');

        return response()->json($this->batchPayload($batch));
    }

    public function updateMapping(Request $request, ProductImportBatch $batch): JsonResponse
    {
        $this->authorize('update', $batch);

        $validated = $request->validate([
            'column_mapping' => ['required', 'array'],
        ]);

        $mapping = [];

        foreach ($validated['column_mapping'] as $field => $index) {
            $mapping[$field] = ($index === '' || $index === null) ? null : (int) $index;
        }

        $batch = $this->imports->saveMapping($batch, $mapping);

        return response()->json($this->batchPayload($batch));
    }

    public function validateBatch(ProductImportBatch $batch): JsonResponse
    {
        $this->authorize('manage', $batch);

        $batch = $this->imports->runValidation($batch);
        $batch->loadCount('errors');

        return response()->json($this->batchPayload($batch));
    }

    public function queue(ProductImportBatch $batch): JsonResponse
    {
        $this->authorize('manage', $batch);

        $batch = $this->imports->queueImport($batch);

        return response()->json($this->batchPayload($batch));
    }

    public function exportCatalog(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        abort_unless(AdminAuthorization::can($request->user(), 'products.export'), 403);

        $validated = $request->validate([
            'format' => ['nullable', 'in:csv,xlsx'],
            'funnel_id' => ['nullable', 'integer', 'exists:funnels,id'],
            'status' => ['nullable', 'string'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $path = app(ProductCatalogExportService::class)->export($validated);

        return response()->download($path, basename($path));
    }

    public function downloadErrorReport(ProductImportBatch $batch): StreamedResponse
    {
        $this->authorize('downloadErrors', $batch);

        $path = $this->imports->generateErrorReportCsv($batch);

        return Storage::disk('local')->download(
            $path,
            basename($path),
            ['Content-Type' => 'text/csv'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function batchPayload(ProductImportBatch $batch): array
    {
        return [
            'uuid' => $batch->uuid,
            'status' => $batch->status->value,
            'original_filename' => $batch->original_filename,
            'format' => $batch->format->value,
            'headers' => $batch->headers,
            'column_mapping' => $batch->column_mapping,
            'preview_rows' => $batch->preview_rows,
            'stats' => $this->publicStats($batch),
            'target_funnel_id' => $batch->target_funnel_id,
            'failure_message' => $batch->failure_message,
            'validated_at' => $batch->validated_at?->toIso8601String(),
            'queued_at' => $batch->queued_at?->toIso8601String(),
            'started_at' => $batch->started_at?->toIso8601String(),
            'completed_at' => $batch->completed_at?->toIso8601String(),
            'errors_count' => $batch->errors_count ?? $batch->errors()->count(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function publicStats(ProductImportBatch $batch): ?array
    {
        $stats = $batch->stats;

        if (! is_array($stats)) {
            return null;
        }

        unset($stats['validated_products']);

        return $stats;
    }
}
