<?php

namespace App\Modules\Email\Http\Controllers;

use App\Models\EmailLog;
use App\Models\EmailProvider;
use App\Models\EmailQueueItem;
use App\Models\EmailTemplate;
use App\Models\Order;
use App\Modules\Email\Enums\EmailQueueStatus;
use App\Modules\Email\Data\EmailSendRequest;
use App\Modules\Email\Services\EmailAnalyticsService;
use App\Modules\Email\Services\EmailDispatchService;
use App\Modules\Email\Services\EmailProviderManager;
use App\Modules\Email\Services\EmailTemplateRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class EmailApiController extends Controller
{
    public function send(Request $request, EmailDispatchService $dispatch): JsonResponse
    {
        $validated = $request->validate([
            'template_slug' => ['required', 'string'],
            'recipient_email' => ['required', 'email'],
            'recipient_name' => ['nullable', 'string'],
            'context_type' => ['nullable', 'string'],
            'context_id' => ['nullable', 'integer'],
        ]);

        $template = EmailTemplate::query()->where('slug', $validated['template_slug'])->firstOrFail();
        $context = $this->resolveContext($validated['context_type'] ?? null, $validated['context_id'] ?? null);

        $item = $dispatch->queueTemplate(
            template: $template,
            recipientEmail: $validated['recipient_email'],
            recipientName: $validated['recipient_name'] ?? null,
            context: $context,
        );

        return response()->json(['data' => $item->fresh()], 202);
    }

    public function queue(Request $request, EmailDispatchService $dispatch): JsonResponse
    {
        return $this->send($request, $dispatch);
    }

    public function retry(EmailQueueItem $item, EmailDispatchService $dispatch): JsonResponse
    {
        return response()->json(['data' => $dispatch->retry($item)]);
    }

    public function cancel(EmailQueueItem $item): JsonResponse
    {
        $item->update(['status' => EmailQueueStatus::Cancelled]);

        return response()->json(['data' => $item->fresh()]);
    }

    public function preview(Request $request, EmailTemplateRenderer $renderer): JsonResponse
    {
        $validated = $request->validate([
            'template_slug' => ['required', 'string'],
            'context_type' => ['nullable', 'string'],
            'context_id' => ['nullable', 'integer'],
        ]);

        $template = EmailTemplate::query()->where('slug', $validated['template_slug'])->firstOrFail();
        $context = $this->resolveContext($validated['context_type'] ?? null, $validated['context_id'] ?? null);

        return response()->json(['data' => $renderer->render($template, $context)]);
    }

    public function templates(): JsonResponse
    {
        return response()->json(['data' => EmailTemplate::query()->orderBy('name')->get()]);
    }

    public function providers(): JsonResponse
    {
        return response()->json(['data' => EmailProvider::query()->orderBy('priority')->get()]);
    }

    public function logs(Request $request): JsonResponse
    {
        $logs = EmailLog::query()
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate((int) $request->query('per_page', 25));

        return response()->json($logs);
    }

    public function analytics(Request $request, EmailAnalyticsService $analytics): JsonResponse
    {
        return response()->json([
            'data' => $analytics->dashboard(
                from: $request->date('from'),
                to: $request->date('to'),
            ),
        ]);
    }

    public function testProvider(EmailProvider $provider, EmailProviderManager $manager): JsonResponse
    {
        $result = $manager->testProvider($provider);

        return response()->json(['data' => $result]);
    }

    private function resolveContext(?string $type, ?int $id): ?Order
    {
        if ($type === Order::class && $id !== null) {
            return Order::query()->find($id);
        }

        return null;
    }
}
