<?php

namespace App\Modules\Fulfillment\Http\Controllers;

use App\Models\Fulfillment;
use App\Modules\Fulfillment\Services\FulfillmentOrchestratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FulfillmentApiController
{
    public function show(string $uuid): JsonResponse
    {
        $fulfillment = Fulfillment::query()
            ->with(['order', 'items', 'timelineEvents', 'warehouse', 'fulfillmentHouse'])
            ->where('uuid', $uuid)
            ->firstOrFail();

        return response()->json(['data' => $fulfillment]);
    }

    public function updateTracking(Request $request, string $uuid, FulfillmentOrchestratorService $orchestrator): JsonResponse
    {
        $fulfillment = Fulfillment::query()->where('uuid', $uuid)->firstOrFail();

        $data = $request->validate([
            'tracking_number' => ['required', 'string'],
            'carrier' => ['nullable', 'string'],
            'shipping_method' => ['nullable', 'string'],
        ]);

        $updated = $orchestrator->updateTracking(
            $fulfillment,
            $data['tracking_number'],
            $data['carrier'] ?? null,
            $data['shipping_method'] ?? null,
        );

        return response()->json(['data' => $updated]);
    }

    public function cancel(Request $request, string $uuid, FulfillmentOrchestratorService $orchestrator): JsonResponse
    {
        $fulfillment = Fulfillment::query()->where('uuid', $uuid)->firstOrFail();
        $updated = $orchestrator->cancel($fulfillment, $request->input('reason'));

        return response()->json(['data' => $updated]);
    }
}
